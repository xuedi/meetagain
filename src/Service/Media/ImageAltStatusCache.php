<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use App\Service\Config\LanguageService;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

/**
 * Per-image missing-alt status in a shared cache pool. Values are computed against the current
 * locale context, which plugins can narrow per request - warm reads therefore belong only on
 * surfaces resolving in the unnarrowed default context (the system images admin list); every
 * other call site invalidates only.
 */
class ImageAltStatusCache
{
    private const string KEY_PREFIX = 'image_alt_status.';

    private bool $cacheFailureLogged = false;

    public function __construct(
        #[Autowire(service: 'cache.image_alt_status')]
        private readonly CacheItemPoolInterface $pool,
        private readonly AltLocaleRequirementResolver $altLocaleRequirementResolver,
        private readonly LanguageService $languageService,
        private readonly LoggerInterface $logger,
    ) {}

    /**
     * @param list<Image> $images
     * @return array<int, bool> imageId => has missing alt
     */
    public function getMissingAltMap(array $images): array
    {
        if ($images === []) {
            return [];
        }

        $keysById = [];
        foreach ($images as $image) {
            $keysById[(int) $image->getId()] = self::KEY_PREFIX . (int) $image->getId();
        }

        try {
            $itemsByKey = [];
            foreach ($this->pool->getItems(array_values($keysById)) as $key => $item) {
                $itemsByKey[$key] = $item;
            }
        } catch (Throwable $exception) {
            $this->logCacheFailureOnce($exception);

            return $this->toMissingFlags($this->computeStatus($images));
        }

        $result = [];
        $misses = [];
        foreach ($images as $image) {
            $id = (int) $image->getId();
            $item = $itemsByKey[$keysById[$id]] ?? null;
            $value = $item !== null && $item->isHit() ? $item->get() : null;
            if (is_array($value) && is_array($value['missing'] ?? null)) {
                $result[$id] = $value['missing'] !== [];
            } else {
                $misses[] = $image;
            }
        }

        if ($misses === []) {
            return $result;
        }

        $computed = $this->computeStatus($misses);
        foreach ($misses as $image) {
            $id = (int) $image->getId();
            $result[$id] = $computed[$id]['missing'] !== [];
            $item = $itemsByKey[$keysById[$id]] ?? null;
            if ($item instanceof CacheItemInterface) {
                $item->set($computed[$id]);
                $this->pool->saveDeferred($item);
            }
        }

        try {
            $this->pool->commit();
        } catch (Throwable $exception) {
            $this->logCacheFailureOnce($exception);
        }

        return $result;
    }

    /**
     * @param list<Image> $images
     */
    public function warm(array $images): void
    {
        $this->getMissingAltMap($images);
    }

    public function invalidateImage(int $imageId): void
    {
        try {
            $this->pool->deleteItem(self::KEY_PREFIX . $imageId);
        } catch (Throwable $exception) {
            $this->logCacheFailureOnce($exception);
        }
    }

    public function invalidateAll(): void
    {
        try {
            $this->pool->clear();
        } catch (Throwable $exception) {
            $this->logCacheFailureOnce($exception);
        }
    }

    /**
     * @param list<Image> $images
     * @return array<int, array{required: list<string>, missing: list<string>}>
     */
    private function computeStatus(array $images): array
    {
        $requiredByImageId = $this->altLocaleRequirementResolver->getRequiredAltLocalesForImages($images);
        $sourceLocale = $this->languageService->getFilteredDefaultLocale();

        $result = [];
        foreach ($images as $image) {
            $required = $requiredByImageId[(int) $image->getId()] ?? [];
            $result[(int) $image->getId()] = [
                'required' => $required,
                'missing' => $image->missingAltLocales($required, $sourceLocale),
            ];
        }

        return $result;
    }

    /**
     * @param array<int, array{required: list<string>, missing: list<string>}> $status
     * @return array<int, bool>
     */
    private function toMissingFlags(array $status): array
    {
        return array_map(static fn(array $entry): bool => $entry['missing'] !== [], $status);
    }

    private function logCacheFailureOnce(Throwable $exception): void
    {
        if ($this->cacheFailureLogged) {
            return;
        }
        $this->cacheFailureLogged = true;
        $this->logger->warning('Image alt status cache backend unreachable, computing directly', [
            'exception' => $exception->getMessage(),
        ]);
    }
}
