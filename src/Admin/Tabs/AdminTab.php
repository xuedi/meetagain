<?php declare(strict_types=1);

namespace App\Admin\Tabs;

final readonly class AdminTab
{
    public function __construct(
        public string $label,
        public string $target,
        public ?string $icon = null,
        public bool $isActive = false,
    ) {}
}
