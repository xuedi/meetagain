<?php declare(strict_types=1);

namespace App\Admin\Navigation;

final readonly class AdminLink
{
    public function __construct(
        public string $label,
        public string $route,
        public ?string $active = null,
        public ?string $role = null,
    ) {}
}
