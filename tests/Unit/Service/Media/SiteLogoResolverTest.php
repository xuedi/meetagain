<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Publisher\SiteLogo\SiteLogoProviderInterface;
use App\Repository\ImageRepository;
use App\Service\Config\ConfigService;
use App\Service\Media\SiteLogoResolver;
use App\Service\Media\ThumbnailSizeFormat;
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

    private function logo(string $hash): Image
    {
        $image = new Image();
        $image->setHash($hash);
        $image->setType(ImageType::SiteLogo);

        return $image;
    }

    private function resolver(array $providers): SiteLogoResolver
    {
        return new SiteLogoResolver(
            providers: $providers,
            configService: $this->configServiceStub,
            imageRepository: $this->imageRepositoryStub,
            assetPackages: $this->packagesStub,
            thumbnailSizeFormat: new ThumbnailSizeFormat(),
        );
    }

    public function testProviderOverrideWinsOverEverything(): void
    {
        $provider = $this->createStub(SiteLogoProviderInterface::class);
        $provider->method('resolveSiteLogo')->willReturn($this->logo('groupHash'));
        $this->configServiceStub->method('getSiteLogoId')->willReturn(99);

        static::assertSame(
            '/images/thumbnails/groupHash_h120.webp',
            $this->resolver([$provider])->resolveUrl(),
        );
    }

    public function testSiteLogoWinsOverFallback(): void
    {
        $provider = $this->createStub(SiteLogoProviderInterface::class);
        $provider->method('resolveSiteLogo')->willReturn(null);
        $provider->method('resolveFallbackSiteLogo')->willReturn($this->logo('fallbackHash'));
        $this->configServiceStub->method('getSiteLogoId')->willReturn(42);
        $this->imageRepositoryStub->method('find')->willReturn($this->logo('abc123'));

        static::assertSame(
            '/images/thumbnails/abc123_h120.webp',
            $this->resolver([$provider])->resolveUrl(),
        );
    }

    public function testFallbackUsedWhenNoSiteLogoConfigured(): void
    {
        $provider = $this->createStub(SiteLogoProviderInterface::class);
        $provider->method('resolveSiteLogo')->willReturn(null);
        $provider->method('resolveFallbackSiteLogo')->willReturn($this->logo('fallbackHash'));
        $this->configServiceStub->method('getSiteLogoId')->willReturn(null);

        static::assertSame(
            '/images/thumbnails/fallbackHash_h120.webp',
            $this->resolver([$provider])->resolveUrl(),
        );
    }

    public function testFallsThroughToDefaultAssetWhenNothingMatches(): void
    {
        $provider = $this->createStub(SiteLogoProviderInterface::class);
        $provider->method('resolveSiteLogo')->willReturn(null);
        $provider->method('resolveFallbackSiteLogo')->willReturn(null);
        $this->configServiceStub->method('getSiteLogoId')->willReturn(null);

        static::assertSame('/build/images/logo-default.webp', $this->resolver([$provider])->resolveUrl());
    }

    public function testFallsThroughToDefaultAssetWhenConfiguredImageMissing(): void
    {
        $this->configServiceStub->method('getSiteLogoId')->willReturn(42);
        $this->imageRepositoryStub->method('find')->willReturn(null);

        static::assertSame('/build/images/logo-default.webp', $this->resolver([])->resolveUrl());
    }

    public function testResolveReportsTheFixedAxisOnlyAndLeavesTheFreeOneNull(): void
    {
        $provider = $this->createStub(SiteLogoProviderInterface::class);
        $provider->method('resolveSiteLogo')->willReturn($this->logo('wideHash'));

        static::assertSame(
            ['url' => '/images/thumbnails/wideHash_h120.webp', 'width' => null, 'height' => 120],
            $this->resolver([$provider])->resolve(),
        );
    }

    public function testDefaultAssetCarriesNoDimensions(): void
    {
        $this->configServiceStub->method('getSiteLogoId')->willReturn(null);

        static::assertSame(
            ['url' => '/build/images/logo-default.webp', 'width' => null, 'height' => null],
            $this->resolver([])->resolve(),
        );
    }
}
