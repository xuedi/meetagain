<?php declare(strict_types=1);

namespace App\Service\Notification\User;

readonly class NotificationSummary
{
    /**
     * @param array<NotificationItem> $items
     */
    public function __construct(
        public array $items,
        public int $totalCount,
    ) {}

    public function hasNotifications(): bool
    {
        return $this->totalCount > 0;
    }

    public function mostUrgentLevel(): ?Level
    {
        $mostUrgent = null;
        foreach ($this->items as $item) {
            if ($mostUrgent === null || $item->level->urgency() > $mostUrgent->urgency()) {
                $mostUrgent = $item->level;
            }
        }

        return $mostUrgent;
    }
}
