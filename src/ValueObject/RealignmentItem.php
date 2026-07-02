<?php declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\RealignmentOutcome;
use DateTimeImmutable;

final readonly class RealignmentItem
{
    public function __construct(
        public int $eventId,
        public DateTimeImmutable $currentStart,
        public ?DateTimeImmutable $currentStop,
        public ?DateTimeImmutable $newStart,
        public ?DateTimeImmutable $newStop,
        public int $rsvpCount,
        public RealignmentOutcome $outcome,
    ) {}
}
