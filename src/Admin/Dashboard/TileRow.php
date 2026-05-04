<?php declare(strict_types=1);

namespace App\Admin\Dashboard;

final readonly class TileRow
{
    /**
     * @param list<string|int> $cells
     */
    public function __construct(
        public array $cells,
        public bool $highlight = false,
    ) {}
}
