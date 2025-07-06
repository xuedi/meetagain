<?php declare(strict_types=1);

namespace Plugin\Karaoke;

use App\Plugin;

class Kernel implements Plugin
{
    public function getName(): string
    {
        return 'Karaoke';
    }

    public function install(): void
    {
        // TODO: Implement install() method.
        // run local migrations and so on
    }

    public function uninstall(): void
    {
        // TODO: Implement uninstall() method.
    }
}