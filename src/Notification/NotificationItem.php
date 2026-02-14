<?php

declare(strict_types=1);

namespace App\Notification;

readonly class NotificationItem
{
    public function __construct(
        public string $label,
        public ?string $icon = null,
        public ?string $route = null,
        public array $routeParams = [],
    ) {}
}
