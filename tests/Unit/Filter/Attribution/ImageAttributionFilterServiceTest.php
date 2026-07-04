<?php declare(strict_types=1);

namespace App\Tests\Unit\Filter\Attribution;

use App\Filter\Attribution\ImageAttributionFilterInterface;
use App\Filter\Attribution\ImageAttributionFilterService;
use PHPUnit\Framework\TestCase;

class ImageAttributionFilterServiceTest extends TestCase
{
    public function testNoFiltersReturnsNull(): void
    {
        // Arrange
        $service = new ImageAttributionFilterService([]);

        // Act
        $result = $service->getVisibleImageIdFilter();

        // Assert
        static::assertNull($result);
    }

    public function testNullFilterIsIgnored(): void
    {
        // Arrange
        $service = new ImageAttributionFilterService([$this->filter(0, null)]);

        // Act
        $result = $service->getVisibleImageIdFilter();

        // Assert
        static::assertNull($result);
    }

    public function testSingleWhitelistIsReturned(): void
    {
        // Arrange
        $service = new ImageAttributionFilterService([$this->filter(0, [1, 2, 3])]);

        // Act
        $result = $service->getVisibleImageIdFilter();

        // Assert
        static::assertSame([1, 2, 3], $result);
    }

    public function testMultipleFiltersAreIntersected(): void
    {
        // Arrange
        $service = new ImageAttributionFilterService([
            $this->filter(100, [1, 2, 3]),
            $this->filter(50, [2, 3, 4]),
        ]);

        // Act
        $result = $service->getVisibleImageIdFilter();

        // Assert
        static::assertSame([2, 3], $result);
    }

    public function testEmptyArrayFilterBlocksAll(): void
    {
        // Arrange
        $service = new ImageAttributionFilterService([
            $this->filter(100, [1, 2, 3]),
            $this->filter(50, []),
        ]);

        // Act
        $result = $service->getVisibleImageIdFilter();

        // Assert
        static::assertSame([], $result);
    }

    private function filter(int $priority, ?array $ids): ImageAttributionFilterInterface
    {
        $filter = $this->createStub(ImageAttributionFilterInterface::class);
        $filter->method('getPriority')->willReturn($priority);
        $filter->method('getVisibleImageIdFilter')->willReturn($ids);

        return $filter;
    }
}
