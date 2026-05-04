<?php declare(strict_types=1);

namespace App\Admin\Dashboard;

final readonly class TileDataset
{
    /**
     * @param list<int> $data
     */
    public function __construct(
        public string $label,
        public array $data,
        public string $borderColor,
    ) {}
}
