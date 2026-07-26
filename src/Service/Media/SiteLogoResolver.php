<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Publisher\SiteLogo\SiteLogoUrlProviderInterface;
use App\Repository\ImageRepository;
use App\Service\Config\ConfigService;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class SiteLogoResolver
{
    /**
     * @param iterable<SiteLogoUrlProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(SiteLogoUrlProviderInterface::class)]
        private iterable $providers,
        private ConfigService $configService,
        private ImageRepository $imageRepository,
        private Packages $assetPackages,
    ) {}

    public function resolveUrl(): string
    {
        foreach ($this->providers as $provider) {
            $url = $provider->resolveSiteLogoUrl();
            if ($url !== null) {
                return $url;
            }
        }

        $logoId = $this->configService->getSiteLogoId();
        if ($logoId !== null) {
            $image = $this->imageRepository->find($logoId);
            if ($image !== null) {
                $url = sprintf('/images/thumbnails/%s_100x100.webp', $image->getHash());
                if ($image->getUpdatedAt() !== null) {
                    $url .= '?v' . $image->getUpdatedAt()->format('YmdHis');
                }

                return $url;
            }
        }

        foreach ($this->providers as $provider) {
            $url = $provider->resolveFallbackSiteLogoUrl();
            if ($url !== null) {
                return $url;
            }
        }

        return $this->assetPackages->getUrl('images/logo.webp');
    }
}
