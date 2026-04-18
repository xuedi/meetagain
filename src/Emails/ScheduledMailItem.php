<?php declare(strict_types=1);

namespace App\Emails;

use DateTimeImmutable;

final readonly class ScheduledMailItem
{
    public function __construct(
        public string $mailType,
        public string $label,
        public DateTimeImmutable $expectedTime,
        public int $expectedRecipients,
    ) {}
}
