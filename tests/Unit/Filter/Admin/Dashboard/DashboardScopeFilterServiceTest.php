<?php declare(strict_types=1);

namespace App\Tests\Unit\Filter\Admin\Dashboard;

use App\Filter\Admin\Dashboard\DashboardScopeFilterInterface;
use App\Filter\Admin\Dashboard\DashboardScopeFilterService;
use PHPUnit\Framework\TestCase;

final class DashboardScopeFilterServiceTest extends TestCase
{
    public function testNoFiltersProducesPlatformWideScope(): void
    {
        // Arrange
        $service = new DashboardScopeFilterService([]);

        // Act
        $scope = $service->resolveScope();

        // Assert
        static::assertTrue($scope->isPlatformWide());
        static::assertNull($scope->eventIds());
        static::assertNull($scope->userIds());
    }

    public function testAllFiltersReturningNullKeepsScopePlatformWide(): void
    {
        // Arrange
        $service = new DashboardScopeFilterService([
            $this->filter(eventIds: null, userIds: null),
            $this->filter(eventIds: null, userIds: null),
        ]);

        // Act
        $scope = $service->resolveScope();

        // Assert
        static::assertTrue($scope->isPlatformWide());
    }

    public function testSingleFilterRestrictsScope(): void
    {
        // Arrange
        $service = new DashboardScopeFilterService([
            $this->filter(eventIds: [1, 2, 3], userIds: [10, 20]),
        ]);

        // Act
        $scope = $service->resolveScope();

        // Assert
        static::assertFalse($scope->isPlatformWide());
        static::assertSame([1, 2, 3], $scope->eventIds());
        static::assertSame([10, 20], $scope->userIds());
    }

    public function testMultipleFiltersIntersectResults(): void
    {
        // Arrange
        $service = new DashboardScopeFilterService([
            $this->filter(eventIds: [1, 2, 3, 4], userIds: [10, 20, 30]),
            $this->filter(eventIds: [2, 3, 5], userIds: [10, 30, 40]),
        ]);

        // Act
        $scope = $service->resolveScope();

        // Assert
        static::assertSame([2, 3], $scope->eventIds());
        static::assertSame([10, 30], $scope->userIds());
    }

    public function testEmptyArrayFromAnyFilterShortCircuitsToEmptyScope(): void
    {
        // Arrange
        $service = new DashboardScopeFilterService([
            $this->filter(eventIds: [1, 2], userIds: [10]),
            $this->filter(eventIds: [], userIds: null),
        ]);

        // Act
        $scope = $service->resolveScope();

        // Assert
        static::assertTrue($scope->isEmpty());
        static::assertSame([], $scope->eventIds());
    }

    public function testNullFilterDoesNotIntersectWithRestrictedFilter(): void
    {
        // Arrange
        $service = new DashboardScopeFilterService([
            $this->filter(eventIds: null, userIds: null),
            $this->filter(eventIds: [5, 6], userIds: [50]),
        ]);

        // Act
        $scope = $service->resolveScope();

        // Assert
        static::assertSame([5, 6], $scope->eventIds());
        static::assertSame([50], $scope->userIds());
    }

    public function testHigherPriorityFiltersAreOrderedFirst(): void
    {
        // Arrange: lower priority must still intersect, regardless of order
        $low = $this->filter(eventIds: [2, 3], userIds: null, priority: 10);
        $high = $this->filter(eventIds: [1, 2, 3, 4], userIds: null, priority: 100);
        $service = new DashboardScopeFilterService([$low, $high]);

        // Act
        $scope = $service->resolveScope();

        // Assert
        static::assertSame([2, 3], $scope->eventIds());
    }

    /**
     * @param array<int>|null $eventIds
     * @param array<int>|null $userIds
     */
    private function filter(?array $eventIds, ?array $userIds, int $priority = 50): DashboardScopeFilterInterface
    {
        return new readonly class($eventIds, $userIds, $priority) implements DashboardScopeFilterInterface {
            public function __construct(
                private ?array $eventIds,
                private ?array $userIds,
                private int $priority,
            ) {}

            public function getPriority(): int
            {
                return $this->priority;
            }

            public function getEventIdFilter(): ?array
            {
                return $this->eventIds;
            }

            public function getUserIdFilter(): ?array
            {
                return $this->userIds;
            }
        };
    }
}
