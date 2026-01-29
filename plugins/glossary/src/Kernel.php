<?php declare(strict_types=1);

namespace Plugin\Glossary;

use App\Entity\AdminSection;
use App\Plugin;
use Symfony\Component\Console\Output\OutputInterface;

class Kernel implements Plugin
{
    public function getPluginKey(): string
    {
        return 'glossary';
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
        // No pre-fixture tasks for this plugin
    }

    public function postFixtures(OutputInterface $output): void
    {
        // No post-fixture tasks for this plugin
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
        // No cron tasks for this plugin
    }

    public function getEventListItemTags(int $eventId): array
    {
        return [];
    }
}
