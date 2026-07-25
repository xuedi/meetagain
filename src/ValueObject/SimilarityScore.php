<?php declare(strict_types=1);

namespace App\ValueObject;

/**
 * Percent-changed between two events for one locale: 0 means identical content,
 * 100 means nothing in common. `total` is the weighted composite of the three fields.
 */
final readonly class SimilarityScore
{
    public function __construct(
        public float $total,
        public float $title,
        public float $teaser,
        public float $description,
    ) {}

    public function exceeds(int $thresholdPercent): bool
    {
        return $this->total >= $thresholdPercent;
    }
}
