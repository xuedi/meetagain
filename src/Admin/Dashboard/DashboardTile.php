<?php declare(strict_types=1);

namespace App\Admin\Dashboard;

/**
 * A dashboard tile: `partial()` names a Twig partial in `templates/admin/_components/dashboard/`,
 * `title()` a translation key.
 */
interface DashboardTile
{
    public function partial(): string;

    public function title(): string;
}
