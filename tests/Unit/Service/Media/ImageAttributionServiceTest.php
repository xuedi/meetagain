<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Filter\Attribution\ImageAttributionFilterService;
use App\Repository\ImageRepository;
use App\Service\Media\ImageAttributionService;
use PHPUnit\Framework\TestCase;

class ImageAttributionServiceTest extends TestCase
{
    public function testVisibleAttributedImagesPassesFilterResultToRepository(): void
    {
        // Arrange
        $image = new Image();
        $filterService = $this->createStub(ImageAttributionFilterService::class);
        $filterService->method('getVisibleImageIdFilter')->willReturn([1, 2]);

        $repository = $this->createMock(ImageRepository::class);
        $repository->expects($this->once())->method('findAttributed')->with([1, 2])->willReturn([$image]);

        $service = new ImageAttributionService($repository, $filterService);

        // Act
        $result = $service->getVisibleAttributedImages();

        // Assert
        static::assertSame([$image], $result);
    }

    public function testHasAnyDelegatesToRepositoryWithFilter(): void
    {
        // Arrange
        $filterService = $this->createStub(ImageAttributionFilterService::class);
        $filterService->method('getVisibleImageIdFilter')->willReturn(null);

        $repository = $this->createMock(ImageRepository::class);
        $repository->expects($this->once())->method('hasAttributed')->with(null)->willReturn(true);

        $service = new ImageAttributionService($repository, $filterService);

        // Act
        $result = $service->hasAny();

        // Assert
        static::assertTrue($result);
    }
}
