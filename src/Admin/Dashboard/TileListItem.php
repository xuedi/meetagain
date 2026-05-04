<?php declare(strict_types=1);

namespace App\Admin\Dashboard;

final readonly class TileListItem
{
    public function __construct(
        public string $label,
        public ?string $sublabel = null,
        public ?string $link = null,
    ) {}
}
