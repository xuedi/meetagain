<?php declare(strict_types=1);

namespace App\Admin\Top\Actions;

final readonly class AdminTopActionDropdownOption
{
    public function __construct(
        public string $label,
        public string $target,
        public bool $isActive = false,
        public ?int $count = null,
    ) {}
}
