<?php declare(strict_types=1);

namespace Plugin\Bookclub;

use App\Plugin;
use Symfony\Component\Console\Output\OutputInterface;

class Kernel implements Plugin
{
    public function getPluginKey(): string
    {
        return 'bookclub';
    }

    public function getMenuLinks(): array
    {
        return [];
    }

    public function getEventTile(int $eventId): ?string
    {
        return null;
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
    }
}
