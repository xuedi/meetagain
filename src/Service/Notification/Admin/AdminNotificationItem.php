<?php declare(strict_types=1);

namespace App\Service\Notification\Admin;

use Symfony\Contracts\Translation\TranslatableInterface;

readonly class AdminNotificationItem
{
    public function __construct(
        public string|TranslatableInterface $label,
        public ?string $route = null,
        public array $routeParams = [],
    ) {}
}
