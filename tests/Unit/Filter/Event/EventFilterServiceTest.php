<?php declare(strict_types=1);

namespace App\Tests\Unit\Filter\Event;

use App\Filter\Event\EventFilterInterface;
use App\Filter\Event\EventFilterService;
use PHPUnit\Framework\TestCase;

class EventFilterServiceTest extends TestCase
{
    public function testGetAccessibleEventIdsWithNoFilters(): void
    {
        // Arrange
        $service = new EventFilterService([], []);

        // Act
        $result = $service->getAccessibleEventIds([1, 2, 3]);

        // Assert
        static::assertSame([1, 2, 3], $result);
    }

    public function testGetAccessibleEventIdsIgnoresAbstainingFilter(): void
    {
        // Arrange
        $service = new EventFilterService([$this->makeFilter(100, null)], []);

        // Act
        $result = $service->getAccessibleEventIds([1, 2, 3]);

        // Assert
        static::assertSame([1, 2, 3], $result);
    }

    public function testGetAccessibleEventIdsIntersectsAcrossFilters(): void
    {
        // Arrange
        $service = new EventFilterService(
            [$this->makeFilter(100, [1, 2, 3]), $this->makeFilter(50, [2, 3, 4])],
            [],
        );

        // Act
        $result = $service->getAccessibleEventIds([1, 2, 3, 4]);

        // Assert
        static::assertSame([2, 3], $result);
    }

    public function testGetAccessibleEventIdsReturnsEmptyWhenAFilterBlocksAll(): void
    {
        // Arrange
        $service = new EventFilterService(
            [$this->makeFilter(100, []), $this->makeFilter(50, [1, 2])],
            [],
        );

        // Act
        $result = $service->getAccessibleEventIds([1, 2]);

        // Assert
        static::assertSame([], $result);
    }

    public function testGetAccessibleEventIdsStopsQueryingOnceNothingIsLeft(): void
    {
        // Arrange
        $blocking = $this->createMock(EventFilterInterface::class);
        $blocking->method('getPriority')->willReturn(200);
        $blocking->expects($this->once())->method('narrowAccessibleEventIds')->willReturn([]);

        $downstream = $this->createMock(EventFilterInterface::class);
        $downstream->method('getPriority')->willReturn(50);
        $downstream->expects($this->never())->method('narrowAccessibleEventIds');

        $service = new EventFilterService([$downstream, $blocking], []);

        // Act
        $result = $service->getAccessibleEventIds([1, 2]);

        // Assert
        static::assertSame([], $result);
    }

    public function testGetAccessibleEventIdsWithEmptyInput(): void
    {
        // Arrange
        $filter = $this->createMock(EventFilterInterface::class);
        $filter->method('getPriority')->willReturn(100);
        $filter->expects($this->never())->method('narrowAccessibleEventIds');

        $service = new EventFilterService([$filter], []);

        // Act
        $result = $service->getAccessibleEventIds([]);

        // Assert
        static::assertSame([], $result);
    }

    /**
     * @param int[]|null $accessible
     */
    private function makeFilter(int $priority, ?array $accessible): EventFilterInterface
    {
        $filter = $this->createStub(EventFilterInterface::class);
        $filter->method('getPriority')->willReturn($priority);
        $filter->method('narrowAccessibleEventIds')->willReturnCallback(
            static fn(array $ids) => $accessible === null ? null : array_values(array_intersect($ids, $accessible)),
        );

        return $filter;
    }
}
