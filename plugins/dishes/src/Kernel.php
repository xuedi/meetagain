<?php declare(strict_types=1);

namespace Plugin\Dishes;

use App\Plugin;

class Kernel implements Plugin
{
    public function getPluginKey(): string
    {
        return 'dishes';
    }

    public function getMenuLinks(): array
    {
        return [];
    }

    public function getEventTile(int $eventId): ?string
    {
        return null;
    }
}
