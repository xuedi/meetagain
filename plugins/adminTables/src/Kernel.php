<?php declare(strict_types=1);

namespace Plugin\AdminTables;

use App\Entity\AdminSection;
use App\Plugin;
use Symfony\Component\Console\Output\OutputInterface;

readonly class Kernel implements Plugin
{
    public function getPluginKey(): string
    {
        return 'adminTables';
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

    public function preFixtures(OutputInterface $output): void
    {
    }

    public function postFixtures(OutputInterface $output): void
    {
    }

    public function getAdminSystemLinks(): ?AdminSection
    {
        return null;
    }

    public function getFooterAbout(): ?string
    {
        return null;
    }

    public function runCronTasks(OutputInterface $output): void
    {
    }

    public function getEventListItemTags(int $eventId): array
    {
        return [];
    }

    public function getMemberPageTop(): ?string
    {
        return null;
    }
}
