<?php declare(strict_types=1);

namespace App\Admin\Tabs;

final readonly class AdminTabs
{
    /**
     * @param list<AdminTab> $tabs
     */
    public function __construct(public array $tabs = []) {}
}
