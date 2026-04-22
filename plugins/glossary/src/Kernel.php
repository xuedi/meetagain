<?php declare(strict_types=1);

namespace Plugin\Glossary;

use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\ValueObject\LinkCollection;
use Symfony\Component\Console\Output\OutputInterface;

class Kernel implements Plugin
{
    public function getPluginKey(): string
    {
        return 'glossary';
    }

    public function getLinkCollection(): LinkCollection
    {
        return LinkCollection::empty();
    }

    public function getEventTile(int $eventId, EventTileLocation $location): ?string
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

    public function getFooterAbout(): ?string
    {
        return null;
    }

    public function getEventListItemTags(int $eventId): array
    {
        return [];
    }

    public function warmCache(WarmCacheType $type, array $ids): void
    {
    }


    public function getStylesheets(): array
    {
        return [];
    }

    public function getJavascripts(): array
    {
        return [];
    }

    public function getOpenApiFragment(): array
    {
        return [];
    }
}
