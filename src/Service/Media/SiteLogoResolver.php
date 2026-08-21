<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use App\Publisher\SiteLogo\SiteLogoProviderInterface;
use App\Repository\ImageRepository;
use App\Service\Config\ConfigService;
use App\Service\Http\RequestHostResolver;
use App\Service\Media\ImageTypes\ImageTypeDefinitionInterface;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class SiteLogoResolver
{
    private const array SIZE = [ImageTypeDefinitionInterface::FREE_AXIS, 120];

    /**
     * @param iterable<SiteLogoProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(SiteLogoProviderInterface::class)]
        private iterable $providers,
        private ConfigService $configService,
        private ImageRepository $imageRepository,
        private Packages $assetPackages,
        private ThumbnailSizeFormat $thumbnailSizeFormat,
        private RequestHostResolver $hostResolver,
    ) {}

    /**
     * @return array{url: string, width: ?int, height: ?int}
     */
    public function resolve(): array
    {
        $image = $this->resolveImage();
        if ($image === null) {
            return ['url' => $this->assetPackages->getUrl('images/logo.webp'), 'width' => null, 'height' => null];
        }

        return ['url' => $this->buildUrl($image), 'width' => null, 'height' => self::SIZE[1]];
    }

    /**
     * @return array{url: string, height: ?int, imageId: ?int}
     */
    public function resolveAbsolute(): array
    {
        $image = $this->resolveImage();
        if ($image === null) {
            return [
                'url' => $this->absolute($this->assetPackages->getUrl('images/logo.webp')),
                'height' => null,
                'imageId' => null,
            ];
        }

        return [
            'url' => $this->absolute($this->buildUrl($image)),
            'height' => self::SIZE[1],
            'imageId' => $image->getId(),
        ];
    }

    private function absolute(string $url): string
    {
        if (str_starts_with($url, 'http://') || str_starts_with($url, 'https://')) {
            return $url;
        }

        return $this->hostResolver->getSchemeAndHost() . '/' . ltrim($url, '/');
    }

    private function resolveImage(): ?Image
    {
        foreach ($this->providers as $provider) {
            $image = $provider->resolveSiteLogo();
            if ($image !== null) {
                return $image;
            }
        }

        $logoId = $this->configService->getSiteLogoId();
        if ($logoId !== null) {
            $image = $this->imageRepository->find($logoId);
            if ($image !== null) {
                return $image;
            }
        }

        foreach ($this->providers as $provider) {
            $image = $provider->resolveFallbackSiteLogo();
            if ($image !== null) {
                return $image;
            }
        }

        return null;
    }

    private function buildUrl(Image $image): string
    {
        $url = sprintf(
            '/images/thumbnails/%s_%s.webp',
            $image->getHash(),
            $this->thumbnailSizeFormat->format(self::SIZE[0], self::SIZE[1]),
        );
        if ($image->getUpdatedAt() !== null) {
            $url .= '?v' . $image->getUpdatedAt()->format('YmdHis');
        }

        return $url;
    }
}
