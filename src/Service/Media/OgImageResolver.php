<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use App\Publisher\OgImage\OgImageProviderInterface;
use App\Publisher\OgImage\ResolvedOgImage;
use App\Repository\ImageRepository;
use App\Service\Config\ConfigService;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class OgImageResolver
{
    private const int OG_WIDTH = 1200;
    private const int OG_HEIGHT = 630;

    /**
     * @param iterable<OgImageProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(OgImageProviderInterface::class)]
        private iterable $providers,
        private ConfigService $configService,
        private ImageRepository $imageRepository,
        private RequestStack $requestStack,
        private TranslatorInterface $translator,
    ) {}

    public function resolve(): ?ResolvedOgImage
    {
        // 1. Plugin-provided override
        foreach ($this->providers as $provider) {
            $resolved = $provider->resolveOgImage();
            if ($resolved !== null) {
                return $resolved;
            }
        }

        // 2. Admin-configured website image (from system config).
        $imageId = $this->configService->getWebsiteImageId();
        if ($imageId === null) {
            return null;
        }

        $image = $this->imageRepository->find($imageId);
        if (!$image instanceof Image) {
            return null;
        }

        // 3. No bundled fallback - if no provider claims and no admin image is set,
        //    callers receive null and the meta tags are simply omitted.
        return new ResolvedOgImage(
            absoluteUrl: $this->buildAbsoluteUrl($image),
            width: self::OG_WIDTH,
            height: self::OG_HEIGHT,
            altText: $this->translator->trans('chrome.og_image_default_alt'),
        );
    }

    private function buildAbsoluteUrl(Image $image): string
    {
        $path = sprintf('/images/thumbnails/%s_%dx%d.webp', $image->getHash(), self::OG_WIDTH, self::OG_HEIGHT);
        if ($image->getUpdatedAt() !== null) {
            $path .= '?v' . $image->getUpdatedAt()->format('YmdHis');
        }

        $request = $this->requestStack->getCurrentRequest();
        $hostPrefix = $request !== null ? $request->getSchemeAndHttpHost() : rtrim($this->configService->getHost(), '/');

        return $hostPrefix . $path;
    }
}
