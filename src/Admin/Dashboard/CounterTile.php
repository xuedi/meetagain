<?php declare(strict_types=1);

namespace App\Admin\Dashboard;

final readonly class CounterTile implements DashboardTile
{
    public function __construct(
        public string $title,
        public int|string $value,
        public ?string $sublabel = null,
        public ?string $icon = null,
        public ?string $link = null,
    ) {}

    public function partial(): string
    {
        return 'counter.html.twig';
    }

    public function title(): string
    {
        return $this->title;
    }
}
