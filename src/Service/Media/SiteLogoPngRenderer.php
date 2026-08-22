<?php declare(strict_types=1);

namespace App\Service\Media;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

readonly class SiteLogoPngRenderer
{
    public const int HEIGHT = 120;

    private const string FALLBACK_ASSET = '/assets/images/logo.webp';
    private const string CACHE_PREFIX = 'site_logo_png_';
    private const int CACHE_TTL = 86400;

    public function __construct(
        private SiteLogoResolver $logoResolver,
        private ImageService $imageService,
        private CacheInterface $cache,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {}

    /**
     * @return array{content: string, etag: string}|null null when nothing renders, including the packaged fallback
     */
    public function render(): ?array
    {
        $image = $this->logoResolver->resolveImage();

        $source = $image !== null
            ? $this->imageService->getSourcePath($image)
            : $this->projectDir . self::FALLBACK_ASSET;

        $version = $image !== null
            ? $image->getHash() . '_' . ($image->getUpdatedAt()?->format('YmdHis') ?? '0')
            : 'packaged_' . (string) (is_file($source) ? filemtime($source) : 0);

        $content = $this->cache->get(
            self::CACHE_PREFIX . sha1($version),
            function (ItemInterface $item) use ($source): string {
                $item->expiresAfter(self::CACHE_TTL);

                return $this->imageService->renderPng($source, self::HEIGHT) ?? '';
            },
        );

        if ($content === '') {
            return null;
        }

        return ['content' => $content, 'etag' => substr(sha1($version), 0, 16)];
    }
}
