<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use App\Publisher\SiteIcon\SiteIconProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class SiteIconResolver
{
    public const string ENDPOINT_PATH = '/icon.png';
    public const int SIZE = 192;

    private const string CACHE_PREFIX = 'site_icon_png_';
    private const int CACHE_TTL = 86400;

    /**
     * @param iterable<SiteIconProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(SiteIconProviderInterface::class)]
        private iterable $providers,
        private ImageService $imageService,
        private CacheInterface $cache,
    ) {}

    public function resolveImage(): ?Image
    {
        foreach ($this->providers as $provider) {
            $image = $provider->resolveSiteIcon();
            if ($image !== null) {
                return $image;
            }
        }

        return null;
    }

    public function url(): ?string
    {
        $image = $this->resolveImage();
        if ($image === null) {
            return null;
        }

        return self::ENDPOINT_PATH . '?v' . (string) $image->getId();
    }

    /**
     * @return array{content: string, etag: string}|null
     */
    public function renderPng(): ?array
    {
        $image = $this->resolveImage();
        if ($image === null) {
            return null;
        }

        $version = $this->version($image);
        $content = $this->cache->get(self::CACHE_PREFIX . sha1($version), function (ItemInterface $item) use ($image): string {
            $item->expiresAfter(self::CACHE_TTL);
            $source = $this->imageService->readOriginal($image);

            return $source === null ? '' : $this->imageService->renderPng($source, self::SIZE) ?? '';
        });

        if ($content === '') {
            return null;
        }

        return ['content' => $content, 'etag' => substr(sha1($version), 0, 16)];
    }

    private function version(Image $image): string
    {
        return $image->getHash() . '_' . ($image->getUpdatedAt()?->format('YmdHis') ?? '0');
    }
}
