<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Filter\Image\SiteLogoUrlFilterInterface;
use App\Repository\ImageRepository;
use App\Service\Config\ConfigService;
use App\Service\Media\SiteLogoResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Asset\Packages;

class SiteLogoResolverTest extends TestCase
{
    private ConfigService $configServiceStub;
    private ImageRepository $imageRepositoryStub;
    private Packages $packagesStub;

    protected function setUp(): void
    {
        $this->configServiceStub = $this->createStub(ConfigService::class);
        $this->imageRepositoryStub = $this->createStub(ImageRepository::class);
        $this->packagesStub = $this->createStub(Packages::class);
        $this->packagesStub->method('getUrl')->willReturn('/build/images/logo-default.webp');
    }

    public function testFilterOverrideWinsOverEverything(): void
    {
        // Arrange: filter returns override URL; configured SiteLogo also exists.
        $filter = $this->createStub(SiteLogoUrlFilterInterface::class);
        $filter->method('resolveSiteLogoUrl')->willReturn('/group/logo.webp');
        $this->configServiceStub->method('getSiteLogoId')->willReturn(99);

        $resolver = new SiteLogoResolver(
            filters: [$filter],
            configService: $this->configServiceStub,
            imageRepository: $this->imageRepositoryStub,
            assetPackages: $this->packagesStub,
        );

        static::assertSame('/group/logo.webp', $resolver->resolveUrl());
    }

    public function testSiteLogoWinsOverFallback(): void
    {
        // Arrange: no override, but both SiteLogo configured AND fallback would match.
        // SiteLogo (admin's explicit choice) must win over the fallback (implicit).
        $filter = $this->createStub(SiteLogoUrlFilterInterface::class);
        $filter->method('resolveSiteLogoUrl')->willReturn(null);
        $filter->method('resolveFallbackSiteLogoUrl')->willReturn('/fallback/logo.webp');
        $this->configServiceStub->method('getSiteLogoId')->willReturn(42);

        $image = new Image();
        $image->setHash('abc123');
        $this->imageRepositoryStub->method('find')->willReturn($image);

        $resolver = new SiteLogoResolver(
            filters: [$filter],
            configService: $this->configServiceStub,
            imageRepository: $this->imageRepositoryStub,
            assetPackages: $this->packagesStub,
        );

        static::assertSame('/images/thumbnails/abc123_100x100.webp', $resolver->resolveUrl());
    }

    public function testFallbackUsedWhenNoSiteLogoConfigured(): void
    {
        // Arrange: no override, no SiteLogo, but fallback returns a URL.
        $filter = $this->createStub(SiteLogoUrlFilterInterface::class);
        $filter->method('resolveSiteLogoUrl')->willReturn(null);
        $filter->method('resolveFallbackSiteLogoUrl')->willReturn('/fallback/logo.webp');
        $this->configServiceStub->method('getSiteLogoId')->willReturn(null);

        $resolver = new SiteLogoResolver(
            filters: [$filter],
            configService: $this->configServiceStub,
            imageRepository: $this->imageRepositoryStub,
            assetPackages: $this->packagesStub,
        );

        static::assertSame('/fallback/logo.webp', $resolver->resolveUrl());
    }

    public function testFallsThroughToDefaultAssetWhenNothingMatches(): void
    {
        // Arrange: no override, no SiteLogo, no fallback.
        $filter = $this->createStub(SiteLogoUrlFilterInterface::class);
        $filter->method('resolveSiteLogoUrl')->willReturn(null);
        $filter->method('resolveFallbackSiteLogoUrl')->willReturn(null);
        $this->configServiceStub->method('getSiteLogoId')->willReturn(null);

        $resolver = new SiteLogoResolver(
            filters: [$filter],
            configService: $this->configServiceStub,
            imageRepository: $this->imageRepositoryStub,
            assetPackages: $this->packagesStub,
        );

        static::assertSame('/build/images/logo-default.webp', $resolver->resolveUrl());
    }

    public function testFallsThroughToDefaultAssetWhenConfiguredImageMissing(): void
    {
        // Arrange: configured id points at a deleted image, no filters.
        $this->configServiceStub->method('getSiteLogoId')->willReturn(42);
        $this->imageRepositoryStub->method('find')->willReturn(null);

        $resolver = new SiteLogoResolver(
            filters: [],
            configService: $this->configServiceStub,
            imageRepository: $this->imageRepositoryStub,
            assetPackages: $this->packagesStub,
        );

        static::assertSame('/build/images/logo-default.webp', $resolver->resolveUrl());
    }
}
