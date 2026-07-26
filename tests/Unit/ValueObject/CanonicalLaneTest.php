<?php declare(strict_types=1);

namespace Tests\Unit\ValueObject;

use App\Enum\CanonicalLaneSegmentType;
use App\Enum\EventCanonicalRootType;
use App\ValueObject\CanonicalLane;
use App\ValueObject\CanonicalLaneSegment;
use App\ValueObject\CanonicalLaneStop;
use PHPUnit\Framework\TestCase;

class CanonicalLaneTest extends TestCase
{
    public function testFollowerRunsCollapseAroundTheOccurrencesThatMovedTheRoot(): void
    {
        // Arrange
        $lane = $this->makeLane([
            $this->stop(1, '2026-01-05', null, rootEventId: 1),
            $this->stop(2, '2026-01-12', null, rootEventId: 1),
            $this->stop(3, '2026-01-19', null, rootEventId: 1),
            $this->stop(4, '2026-01-26', null, rootEventId: 1),
            $this->stop(5, '2026-02-02', EventCanonicalRootType::Detached, rootEventId: 5),
            $this->stop(6, '2026-02-09', null, rootEventId: 1),
            $this->stop(7, '2026-02-16', null, rootEventId: 1),
        ]);

        // Act
        $segments = $lane->segments();

        // Assert
        self::assertSame(
            ['first', 'follower', 'detached', 'follower'],
            array_map(static fn(CanonicalLaneSegment $s) => $s->type->value, $segments),
        );
        self::assertSame([1, 3, 1, 2], array_map(static fn(CanonicalLaneSegment $s) => $s->count, $segments));
    }

    public function testACollapsedRunCarriesItsDateRange(): void
    {
        // Arrange
        $lane = $this->makeLane([
            $this->stop(1, '2026-01-05', null, rootEventId: 1),
            $this->stop(2, '2026-01-12', null, rootEventId: 1),
            $this->stop(3, '2026-06-08', null, rootEventId: 1),
        ]);

        // Act
        $run = $lane->segments()[1];

        // Assert
        self::assertSame(CanonicalLaneSegmentType::Follower, $run->type);
        self::assertSame('2026-01-12', $run->fromDate);
        self::assertSame('2026-06-08', $run->toDate);
    }

    public function testLockedAndCanceledOccurrencesAreCountedIntoTheirRun(): void
    {
        // Arrange
        $lane = $this->makeLane([
            $this->stop(1, '2026-01-05', null, rootEventId: 1),
            $this->stop(2, '2026-01-12', null, rootEventId: 1, locked: true),
            $this->stop(3, '2026-01-19', null, rootEventId: 1, canceled: true),
            $this->stop(4, '2026-01-26', null, rootEventId: 1, locked: true),
        ]);

        // Act
        $run = $lane->segments()[1];

        // Assert
        self::assertSame(2, $run->locked);
        self::assertSame(1, $run->canceled);
    }

    public function testARootMarkerOnTheFirstOccurrenceWinsOverTheImplicitFirst(): void
    {
        // Arrange
        $lane = $this->makeLane([$this->stop(1, '2026-01-05', EventCanonicalRootType::Root, rootEventId: 1)]);

        // Act
        $segments = $lane->segments();

        // Assert
        self::assertCount(1, $segments);
        self::assertSame(CanonicalLaneSegmentType::Root, $segments[0]->type);
    }

    public function testAnEmptyLaneHasNoSegments(): void
    {
        // Arrange
        $lane = $this->makeLane([]);

        // Act & Assert
        self::assertSame([], $lane->segments());
    }

    /**
     * @param list<CanonicalLaneStop> $stops
     */
    private function makeLane(array $stops): CanonicalLane
    {
        return new CanonicalLane(1, 'Weekly Go Study Group', 'en', $stops, 1);
    }

    private function stop(
        int $eventId,
        string $date,
        ?EventCanonicalRootType $marker,
        int $rootEventId,
        bool $locked = false,
        bool $canceled = false,
    ): CanonicalLaneStop {
        return new CanonicalLaneStop(
            eventId: $eventId,
            date: $date,
            title: 'Occurrence ' . $eventId,
            marker: $marker,
            locked: $locked,
            canceled: $canceled,
            rootEventId: $rootEventId,
            percentChanged: 0.0,
        );
    }
}
