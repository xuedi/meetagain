<?php declare(strict_types=1);

namespace App\Moderation;

use DateTimeImmutable;

final readonly class AuthorHistory
{
    public function __construct(
        public int $open,
        public int $dismissed,
        public int $actioned,
        public ?DateTimeImmutable $lastActionedAt,
    ) {}

    public function getTotal(): int
    {
        return $this->open + $this->dismissed + $this->actioned;
    }
}
