<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use App\Filter\Attribution\ImageAttributionFilterService;
use App\Repository\ImageRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ImageAttributionService
{
    public const string CACHE_TAG = 'image_attribution';

    private const string CACHE_KEY_PREFIX = 'image_attribution_present_';

    /** @var array<int>|null */
    private ?array $visibleIds = null;
    private bool $visibleIdsResolved = false;
    private ?bool $hasAny = null;

    public function __construct(
        private readonly ImageRepository $imageRepository,
        private readonly ImageAttributionFilterService $filterService,
        #[Autowire(service: 'cache.image_attribution')]
        private readonly TagAwareCacheInterface $cache,
        private readonly RequestStack $requestStack,
    ) {}

    /**
     * @return array<Image>
     */
    public function getVisibleAttributedImages(): array
    {
        return $this->imageRepository->findAttributed($this->resolveVisibleIds());
    }

    public function hasAny(): bool
    {
        return $this->hasAny ??= $this->cache->get(
            self::CACHE_KEY_PREFIX . $this->scope(),
            function (ItemInterface $item): bool {
                $item->tag(self::CACHE_TAG);

                return $this->imageRepository->hasAttributed($this->resolveVisibleIds());
            },
        );
    }

    public function invalidate(): void
    {
        $this->hasAny = null;
        $this->cache->invalidateTags([self::CACHE_TAG]);
    }

    /**
     * @return array<int>|null
     */
    private function resolveVisibleIds(): ?array
    {
        if (!$this->visibleIdsResolved) {
            $this->visibleIds = $this->filterService->getVisibleImageIdFilter();
            $this->visibleIdsResolved = true;
        }

        return $this->visibleIds;
    }

    private function scope(): string
    {
        return sha1((string) $this->requestStack->getCurrentRequest()?->getHost());
    }
}
