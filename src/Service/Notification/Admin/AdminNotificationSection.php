<?php declare(strict_types=1);

namespace App\Service\Notification\Admin;

use Symfony\Contracts\Translation\TranslatableInterface;

readonly class AdminNotificationSection
{
    /**
     * @param AdminNotificationItem[] $items
     */
    public function __construct(
        public string|TranslatableInterface $title,
        public array $items,
    ) {}
}
