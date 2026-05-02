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

    /**
     * Stable URL-safe identifier for routing into the per-item guard-detail page.
     */
    public function getKey(): string
    {
        return $this->mailType . '_' . $this->expectedTime->getTimestamp();
    }
}
