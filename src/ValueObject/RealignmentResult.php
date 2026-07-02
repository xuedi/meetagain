<?php declare(strict_types=1);

namespace App\ValueObject;

use App\Entity\User;
use DateTimeImmutable;

final readonly class RealignmentResult
{
    /**
     * @param array<int, array{user: User, dates: list<DateTimeImmutable>}> $removedAttendees keyed by user id
     */
    public function __construct(
        public int $movedCount,
        public array $removedAttendees,
    ) {}
}
