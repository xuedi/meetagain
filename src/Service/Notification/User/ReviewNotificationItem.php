<?php

declare(strict_types=1);

namespace App\Service\Notification\User;

readonly class ReviewNotificationItem
{
    public function __construct(
        public string $id,
        public string $description,
        public bool $canDeny = true,
        public ?string $icon = null,
        public ?string $longDescription = null,
    ) {}
}
