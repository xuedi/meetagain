<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Filter\Image\SiteLogoUrlFilterInterface;
use App\Repository\ImageRepository;
use App\Service\Config\ConfigService;
use Symfony\Component\Asset\Packages;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class SiteLogoResolver
{
    /**
     * @param iterable<SiteLogoUrlFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(SiteLogoUrlFilterInterface::class)]
        private iterable $filters,
        private ConfigService $configService,
        private ImageRepository $imageRepository,
        private Packages $assetPackages,
    ) {}

    public function resolveUrl(): string
    {
        // 1. Pre-SiteLogo override (e.g. whitelabel domain-locked group logo).
        foreach ($this->filters as $filter) {
            $url = $filter->resolveSiteLogoUrl();
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
        foreach ($this->filters as $filter) {
            $url = $filter->resolveFallbackSiteLogoUrl();
            if ($url !== null) {
                return $url;
            }
        }

        // 4. Bundled default asset.
        return $this->assetPackages->getUrl('images/logo.webp');
    }
}
