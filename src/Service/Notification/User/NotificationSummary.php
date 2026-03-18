<?php

declare(strict_types=1);

namespace App\Service\Notification\User;

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
