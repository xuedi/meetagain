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
    public function testTheResolvedLogoIsRasterisedFromItsOriginalNotFromTheWebpThumbnail(): void
    {
        // Arrange
        $image = new Image()->setHash('groupHash');
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->once())->method('getSourcePath')->with($image)->willReturn('/data/images/groupHash.jpg');
        $imageService
            ->expects($this->once())
            ->method('renderPng')
            ->with('/data/images/groupHash.jpg', SiteLogoPngRenderer::HEIGHT)
            ->willReturn('png-bytes');

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
        $imageService
            ->expects($this->once())
            ->method('renderPng')
            ->with('/app/assets/images/logo.webp', SiteLogoPngRenderer::HEIGHT)
            ->willReturn('png-bytes');

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

    public function testTheSameLogoIsRasterisedOnceAcrossRepeatedRequests(): void
    {
        // Arrange
        $image = new Image()->setHash('groupHash');
        $image->setUpdatedAt(new DateTimeImmutable('2026-08-01 09:00:00'));
        $imageService = $this->createMock(ImageService::class);
        $imageService->method('getSourcePath')->willReturn('/data/images/groupHash.jpg');
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

        return new SiteLogoPngRenderer($logoResolver, $imageService, new ArrayAdapter(), '/app');
    }
}
