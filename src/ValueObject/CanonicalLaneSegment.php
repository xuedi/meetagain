<?php declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\CanonicalLaneSegmentType;

final readonly class CanonicalLaneSegment
{
    public function __construct(
        public CanonicalLaneSegmentType $type,
        public int $count,
        public string $fromDate,
        public string $toDate,
        public string $title,
        public float $percentChanged,
        public int $locked,
        public int $canceled,
    ) {}
}
