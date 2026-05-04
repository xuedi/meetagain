<?php declare(strict_types=1);

namespace App\Admin\Dashboard;

final readonly class TileHealthCheck
{
    public function __construct(
        public string $label,
        public bool $ok,
        public ?string $detail = null,
    ) {}
}
