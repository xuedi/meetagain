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
        // 1. Plugin-provided override
        foreach ($this->providers as $provider) {
            $url = $provider->resolveSiteLogoUrl();
            if ($url !== null) {
                return $url;
            }
        }

        // 2. Admin-configured SiteLogo (from theme settings).
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

        // 3. Post-SiteLogo fallback (e.g. main-host-group's logo as implicit platform branding).
        foreach ($this->providers as $provider) {
            $url = $provider->resolveFallbackSiteLogoUrl();
            if ($url !== null) {
                return $url;
            }
        }

        // 4. Bundled default asset.
        return $this->assetPackages->getUrl('images/logo.webp');
    }
}
