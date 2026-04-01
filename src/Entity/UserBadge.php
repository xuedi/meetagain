<?php declare(strict_types=1);

namespace App\Entity;

readonly class UserBadge
{
    public function __construct(
        public string $icon,
        public string $title,
        public string $color = 'has-text-success',
    ) {}
}
