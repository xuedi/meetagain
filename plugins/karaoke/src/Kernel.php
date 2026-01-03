<?php declare(strict_types=1);

namespace Plugin\Karaoke;

use App\Plugin;

class Kernel implements Plugin
{
    public function getPluginKey(): string
    {
        return 'karaoke';
    }

    public function registerMenuLinks(): array
    {
        return [];
    }

    public function getEventTile(int $eventId): ?string
    {
        return null;
    }
}
