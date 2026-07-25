<?php declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\EventCanonicalRootType;

final readonly class CanonicalLaneStop
{
    public function __construct(
        public int $eventId,
        public string $date,
        public string $title,
        public ?EventCanonicalRootType $marker,
        public bool $locked,
        public bool $canceled,
        public int $rootEventId,
        public float $percentChanged,
    ) {}

    public function isRoot(): bool
    {
        return $this->rootEventId === $this->eventId;
    }
}
