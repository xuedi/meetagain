<?php declare(strict_types=1);

namespace App\Admin\Dashboard;

final readonly class MultiSeriesChartTile implements DashboardTile
{
    /**
     * @param list<string>       $labels
     * @param list<TileDataset>  $datasets
     */
    public function __construct(
        public string $title,
        public string $canvasId,
        public array $labels,
        public array $datasets,
    ) {}

    public function partial(): string
    {
        return 'multi_chart.html.twig';
    }

    public function title(): string
    {
        return $this->title;
    }

    /**
     * @return array{labels: list<string>, datasets: list<array{label: string, data: list<int>, borderColor: string}>}
     */
    public function payload(): array
    {
        $datasets = [];
        foreach ($this->datasets as $dataset) {
            $datasets[] = [
                'label' => $dataset->label,
                'data' => $dataset->data,
                'borderColor' => $dataset->borderColor,
            ];
        }

        return [
            'labels' => $this->labels,
            'datasets' => $datasets,
        ];
    }
}
