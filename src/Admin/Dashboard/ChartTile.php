<?php declare(strict_types=1);

namespace App\Admin\Dashboard;

final readonly class ChartTile implements DashboardTile
{
    /**
     * @param list<array{x: string, y: int}> $dataset
     */
    public function __construct(
        public string $title,
        public string $canvasId,
        public array $dataset,
        public string $color = 'rgba(54, 162, 235, 0.5)',
    ) {}

    public function partial(): string
    {
        return 'chart.html.twig';
    }

    public function title(): string
    {
        return $this->title;
    }
}
