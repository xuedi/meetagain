<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\Publisher\ImageThumbnail\ImageThumbnailSizeProviderInterface;
use App\Repository\ConfigRepository;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Verifies the plugin provider chain layered on top of ConfigService for
 * thumbnail-size and fit-mode resolution. Kept separate from ConfigServiceTest
 * to keep that class under the per-class method threshold.
 */
class ConfigServiceImageTypeProviderTest extends TestCase
{
    private function build(?ImageThumbnailSizeProviderInterface $provider = null): ConfigService
    {
        $cache = $this->createStub(CacheInterface::class);
        $cache->method('get')->willReturnCallback(
            fn(string $key, callable $cb): mixed => $cb($this->createStub(ItemInterface::class)),
        );

        return new ConfigService(
            repo: $this->createStub(ConfigRepository::class),
            em: $this->createStub(EntityManagerInterface::class),
            cache: $cache,
            kernel: $this->createStub(KernelInterface::class),
            appState: $this->createStub(AppStateService::class),
            thumbnailSizeProviders: $provider !== null ? [$provider] : [],
        );
    }

    public function testProviderOverridesCoreThumbnailSizes(): void
    {
        $provider = $this->createStub(ImageThumbnailSizeProviderInterface::class);
        $provider->method('getThumbnailSizes')->willReturn([[42, 42]]);
        $service = $this->build($provider);

        static::assertSame([[42, 42]], $service->getThumbnailSizes(ImageType::ProfilePicture));
    }

    public function testProviderFallsThroughWhenReturnsNull(): void
    {
        $provider = $this->createStub(ImageThumbnailSizeProviderInterface::class);
        $provider->method('getThumbnailSizes')->willReturn(null);
        $service = $this->build($provider);

        static::assertSame(
            [[400, 400], [350, 350], [100, 100], [80, 80], [50, 50]],
            $service->getThumbnailSizes(ImageType::ProfilePicture),
        );
    }

    public function testFitModeFitForSiteLogoFromCore(): void
    {
        $service = $this->build();
        static::assertSame(ImageFitMode::Fit, $service->getFitMode(ImageType::SiteLogo));
    }

    public function testFitModeCropForEverythingElseFromCore(): void
    {
        $service = $this->build();
        static::assertSame(ImageFitMode::Crop, $service->getFitMode(ImageType::ProfilePicture));
        static::assertSame(ImageFitMode::Crop, $service->getFitMode(ImageType::EventTeaser));
    }

    public function testProviderOverridesFitMode(): void
    {
        $provider = $this->createStub(ImageThumbnailSizeProviderInterface::class);
        $provider->method('getFitMode')->willReturn(ImageFitMode::Fit);
        $service = $this->build($provider);

        static::assertSame(ImageFitMode::Fit, $service->getFitMode(ImageType::ProfilePicture));
    }
}
