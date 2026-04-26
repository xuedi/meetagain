<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\Filter\Image\ImageThumbnailSizeFilterInterface;
use App\Repository\ConfigRepository;
use App\Service\AppStateService;
use App\Service\Config\ConfigService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;

/**
 * Verifies the plugin filter chain layered on top of ConfigService for
 * thumbnail-size and fit-mode resolution. Kept separate from ConfigServiceTest
 * to keep that class under the per-class method threshold.
 */
class ConfigServiceImageTypeFilterTest extends TestCase
{
    private function build(?ImageThumbnailSizeFilterInterface $filter = null): ConfigService
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
            thumbnailSizeFilters: $filter !== null ? [$filter] : [],
        );
    }

    public function testFilterOverridesCoreThumbnailSizes(): void
    {
        // Arrange
        $filter = $this->createStub(ImageThumbnailSizeFilterInterface::class);
        $filter->method('getThumbnailSizes')->willReturn([[42, 42]]);
        $service = $this->build($filter);

        // Act
        $result = $service->getThumbnailSizes(ImageType::ProfilePicture);

        // Assert: filter wins over core ProfilePicture arm
        static::assertSame([[42, 42]], $result);
    }

    public function testFilterFallsThroughWhenReturnsNull(): void
    {
        // Arrange: filter declines, core map handles
        $filter = $this->createStub(ImageThumbnailSizeFilterInterface::class);
        $filter->method('getThumbnailSizes')->willReturn(null);
        $service = $this->build($filter);

        // Act + Assert: core ProfilePicture sizes returned
        static::assertSame(
            [[400, 400], [100, 100], [80, 80], [50, 50]],
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

    public function testFilterOverridesFitMode(): void
    {
        // Arrange: filter forces Fit on a core type
        $filter = $this->createStub(ImageThumbnailSizeFilterInterface::class);
        $filter->method('getFitMode')->willReturn(ImageFitMode::Fit);
        $service = $this->build($filter);

        // Act + Assert
        static::assertSame(ImageFitMode::Fit, $service->getFitMode(ImageType::ProfilePicture));
    }
}
