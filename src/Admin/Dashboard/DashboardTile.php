<?php declare(strict_types=1);

namespace App\Admin\Dashboard;

/**
 * Marker interface for a dashboard tile value object.
 *
 * Implementations expose `partial()` (the basename of the Twig partial under
 * `templates/admin/_components/dashboard/`) and `title()` (the translation key).
 */
interface DashboardTile
{
    public function partial(): string;

    public function title(): string;
}
