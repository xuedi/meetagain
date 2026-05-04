<?php declare(strict_types=1);

namespace App\Admin\Dashboard;

final readonly class HealthTile implements DashboardTile
{
    /**
     * @param list<TileHealthCheck> $checks
     */
    public function __construct(
        public string $title,
        public array $checks,
    ) {}

    public function partial(): string
    {
        return 'health.html.twig';
    }

    public function title(): string
    {
        return $this->title;
    }
}
