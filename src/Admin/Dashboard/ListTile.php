<?php declare(strict_types=1);

namespace App\Admin\Dashboard;

final readonly class ListTile implements DashboardTile
{
    /**
     * @param list<TileListItem> $items
     */
    public function __construct(
        public string $title,
        public array $items,
        public string $emptyMessage = '',
    ) {}

    public function partial(): string
    {
        return 'list.html.twig';
    }

    public function title(): string
    {
        return $this->title;
    }
}
