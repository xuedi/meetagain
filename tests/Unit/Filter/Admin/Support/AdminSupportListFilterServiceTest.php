<?php declare(strict_types=1);

namespace Tests\Unit\Filter\Admin\Support;

use App\Filter\Admin\Support\AdminSupportListFilterInterface;
use App\Filter\Admin\Support\AdminSupportListFilterService;
use PHPUnit\Framework\TestCase;

class AdminSupportListFilterServiceTest extends TestCase
{
    public function testNoFiltersLeavesTheSetUnrestricted(): void
    {
        // Arrange
        $service = new AdminSupportListFilterService([]);

        // Act
        $ids = $service->getRequestIdFilter();

        // Assert
        static::assertNull($ids);
    }

    public function testAFilterWithNoOpinionLeavesTheSetUnrestricted(): void
    {
        // Arrange
        $service = new AdminSupportListFilterService([$this->filter(null)]);

        // Act
        $ids = $service->getRequestIdFilter();

        // Assert
        static::assertNull($ids);
    }

    public function testFiltersIntersectRatherThanUnion(): void
    {
        // Arrange
        $service = new AdminSupportListFilterService([$this->filter([1, 2, 3]), $this->filter([3, 4])]);

        // Act
        $ids = $service->getRequestIdFilter();

        // Assert
        static::assertSame([3], $ids);
    }

    public function testABlockingFilterWinsOverAnAllowList(): void
    {
        // Arrange
        $service = new AdminSupportListFilterService([$this->filter([1, 2]), $this->filter([])]);

        // Act
        $ids = $service->getRequestIdFilter();

        // Assert
        static::assertSame([], $ids);
    }

    public function testAnEmptyIntersectionBlocksEverything(): void
    {
        // Arrange
        $service = new AdminSupportListFilterService([$this->filter([1, 2]), $this->filter([3])]);

        // Act
        $ids = $service->getRequestIdFilter();

        // Assert
        static::assertSame([], $ids);
    }

    /** @param array<int>|null $ids */
    private function filter(?array $ids): AdminSupportListFilterInterface
    {
        $filter = $this->createStub(AdminSupportListFilterInterface::class);
        $filter->method('getRequestIdFilter')->willReturn($ids);

        return $filter;
    }
}
