<?php declare(strict_types=1);

namespace App\Calendar;

use DateTimeImmutable;

final readonly class Entry
{
    public function __construct(
        public string $uid,
        public string $summary,
        public string $description,
        public string $url,
        public DateTimeImmutable $start,
        public DateTimeImmutable $end,
        public ?string $location,
        public bool $cancelled,
    ) {}
}
