<?php declare(strict_types=1);

namespace App\Service\Notification\User;

readonly class NotificationItem
{
    public function __construct(
        public string $label,
        public ?string $icon = null,
        public ?string $route = null,
        public array $routeParams = [],
        public ?string $key = null,
    ) {}

    public function key(): ?string
    {
        return $this->key ?? $this->route;
    }
}
