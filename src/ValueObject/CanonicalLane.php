<?php declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\CanonicalLaneSegmentType;
use App\Enum\EventCanonicalRootType;

final readonly class CanonicalLane
{
    /**
     * @param array<CanonicalLaneStop> $stops
     */
    public function __construct(
        public int $seriesId,
        public string $seriesName,
        public string $locale,
        public array $stops,
        public int $rootCount,
    ) {}

    public function isBranched(): bool
    {
        return $this->rootCount > 1;
    }

    /**
     * The lane as an operator scans it: every occurrence that moved the canonical target keeps its
     * own segment, and the untouched runs between them collapse into one. A series running for
     * years is otherwise hundreds of indistinguishable chips.
     *
     * @return list<CanonicalLaneSegment>
     */
    public function segments(): array
    {
        $segments = [];
        $run = [];

        foreach ($this->stops as $stop) {
            $type = $this->typeOf($stop);

            if ($type === CanonicalLaneSegmentType::Follower) {
                $run[] = $stop;
                continue;
            }

            $segments = [...$segments, ...$this->collapse($run)];
            $run = [];
            $segments[] = new CanonicalLaneSegment(
                type: $type,
                count: 1,
                fromDate: $stop->date,
                toDate: $stop->date,
                title: $stop->title,
                percentChanged: $stop->percentChanged,
                locked: $stop->locked ? 1 : 0,
                canceled: $stop->canceled ? 1 : 0,
            );
        }

        return [...$segments, ...$this->collapse($run)];
    }

    private function typeOf(CanonicalLaneStop $stop): CanonicalLaneSegmentType
    {
        return match (true) {
            $stop->marker === EventCanonicalRootType::Root => CanonicalLaneSegmentType::Root,
            $stop->marker === EventCanonicalRootType::Detached => CanonicalLaneSegmentType::Detached,
            $stop->isRoot() => CanonicalLaneSegmentType::First,
            default => CanonicalLaneSegmentType::Follower,
        };
    }

    /**
     * @param list<CanonicalLaneStop> $run
     * @return list<CanonicalLaneSegment>
     */
    private function collapse(array $run): array
    {
        if ($run === []) {
            return [];
        }

        $locked = 0;
        $canceled = 0;
        foreach ($run as $stop) {
            $locked += $stop->locked ? 1 : 0;
            $canceled += $stop->canceled ? 1 : 0;
        }

        return [new CanonicalLaneSegment(
            type: CanonicalLaneSegmentType::Follower,
            count: count($run),
            fromDate: $run[0]->date,
            toDate: $run[count($run) - 1]->date,
            title: '',
            percentChanged: 0.0,
            locked: $locked,
            canceled: $canceled,
        )];
    }
}
