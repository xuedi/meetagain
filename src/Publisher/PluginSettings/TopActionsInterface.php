<?php declare(strict_types=1);

namespace App\Publisher\PluginSettings;

use App\Admin\Top\AdminTopActionInterface;

/**
 * Lets a descriptor add buttons to the topbar of the global settings page, ahead of Back.
 */
interface TopActionsInterface
{
    /** @return list<AdminTopActionInterface> */
    public function getTopActions(): array;
}
