<?php declare(strict_types=1);

namespace App\ValueObject;

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
}
