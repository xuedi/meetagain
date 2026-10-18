<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Service\Media\ImageService;
use App\Service\Media\SiteLogoPngRenderer;
use App\Service\Media\SiteLogoResolver;
use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

final class SiteLogoPngRendererTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/site-logo-' . bin2hex(random_bytes(8));
        mkdir($this->projectDir . '/assets/images', 0o775, true);
        file_put_contents($this->projectDir . '/assets/images/logo.webp', 'packaged-logo-bytes');
    }

    protected function tearDown(): void
    {
        exec(sprintf('rm -rf %s', escapeshellarg($this->projectDir)));
    }

    public function testTheResolvedLogoIsRasterisedFromItsOriginalNotFromTheWebpThumbnail(): void
    {
        // Arrange
        $image = new Image()->setHash('groupHash');
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->once())->method('readOriginal')->with($image)->willReturn('original-bytes');
        $imageService->expects($this->once())->method('renderPng')->with('original-bytes', SiteLogoPngRenderer::HEIGHT)->willReturn('png-bytes');

        // Act
        $rendered = $this->renderer($image, $imageService)->render();

        // Assert
        static::assertSame('png-bytes', $rendered['content'] ?? null);
        static::assertNotSame('', $rendered['etag'] ?? '');
    }

    public function testAnUnresolvedLogoFallsBackToThePackagedAsset(): void
    {
        // Arrange
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->once())->method('renderPng')->with('packaged-logo-bytes', SiteLogoPngRenderer::HEIGHT)->willReturn('png-bytes');

        // Act
        $rendered = $this->renderer(null, $imageService)->render();

        // Assert
        static::assertSame('png-bytes', $rendered['content'] ?? null);
    }

    public function testAFailedRasterisationReportsNothingRatherThanAnEmptyImage(): void
    {
        // Arrange
        $imageService = $this->createStub(ImageService::class);
        $imageService->method('renderPng')->willReturn(null);

        // Act & Assert
        static::assertNull($this->renderer(null, $imageService)->render());
    }

    public function testAMissingOriginalReportsNothing(): void
    {
        // Arrange
        $image = new Image()->setHash('groupHash');
        $imageService = $this->createMock(ImageService::class);
        $imageService->method('readOriginal')->willReturn(null);
        $imageService->expects($this->never())->method('renderPng');

        // Act & Assert
        static::assertNull($this->renderer($image, $imageService)->render());
    }

    public function testTheSameLogoIsRasterisedOnceAcrossRepeatedRequests(): void
    {
        // Arrange
        $image = new Image()->setHash('groupHash');
        $image->setUpdatedAt(new DateTimeImmutable('2026-08-01 09:00:00'));
        $imageService = $this->createMock(ImageService::class);
        $imageService->method('readOriginal')->willReturn('original-bytes');
        $imageService->expects($this->once())->method('renderPng')->willReturn('png-bytes');
        $renderer = $this->renderer($image, $imageService);

        // Act
        $first = $renderer->render();
        $second = $renderer->render();

        // Assert
        static::assertSame($first, $second);
    }

    private function renderer(?Image $resolved, ImageService $imageService): SiteLogoPngRenderer
    {
        $logoResolver = $this->createStub(SiteLogoResolver::class);
        $logoResolver->method('resolveImage')->willReturn($resolved);

        return new SiteLogoPngRenderer($logoResolver, $imageService, new ArrayAdapter(), $this->projectDir);
    }
}
