<?php declare(strict_types=1);

namespace App\Service\Notification\Admin;

readonly class AdminNotificationItem
{
    public function __construct(
        public string $label,
        public ?string $route = null,
        public array $routeParams = [],
    ) {}
}
