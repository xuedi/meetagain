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

    public function getAdminSystemLinks(): ?AdminSection
    {
        return null;
    }

    public function getStylesheets(): array
    {
        return [];
    }

    public function getJavascripts(): array
    {
        return [];
    }

    public function getFooterAbout(): ?string
    {
        return null;
    }
}
