<?php declare(strict_types=1);

namespace App\Admin\Tabs;

/**
 * Implementations build the tab strip rendered above an admin page.
 */
interface AdminTabsInterface
{
    public function getTabs(): AdminTabs;
}
