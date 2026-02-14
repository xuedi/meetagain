<?php

declare(strict_types=1);

namespace App\Notification;

readonly class NotificationSummary
{
    public function __construct(
        public array $items,
        public int $totalCount,
    ) {}

    public function hasNotifications(): bool
    {
        return $this->totalCount > 0;
    }
}
