<?php declare(strict_types=1);

namespace App\Service\Notification\Admin;

readonly class AdminNotificationSection
{
    /**
     * @param AdminNotificationItem[] $items
     */
    public function __construct(
        public string $title,
        public array $items,
    ) {}
}
