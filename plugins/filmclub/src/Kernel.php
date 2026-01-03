<?php declare(strict_types=1);

namespace Plugin\Filmclub;

use App\Plugin;

class Kernel implements Plugin
{
    public function getPluginKey(): string
    {
        return 'filmclub';
    }
}
