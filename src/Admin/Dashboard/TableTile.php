<?php declare(strict_types=1);

namespace App\Admin\Dashboard;

final readonly class TableTile implements DashboardTile
{
    /**
     * @param list<string>   $headers     Translation keys for column headers (empty list = no header row).
     * @param list<TileRow>  $rows
     */
    public function __construct(
        public string $title,
        public array $rows,
        public array $headers = [],
        public ?TileRow $footerRow = null,
    ) {}

    public function partial(): string
    {
        return 'table.html.twig';
    }

    public function title(): string
    {
        return $this->title;
    }
}
