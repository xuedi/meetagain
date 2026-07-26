<?php declare(strict_types=1);

namespace App\ValueObject;

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
