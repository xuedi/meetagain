<?php declare(strict_types=1);

namespace Plugin\Dishes;

use App\Entity\AdminSection;
use App\Entity\Link;
use App\Enum\WarmCacheType;
use App\Plugin;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

readonly class Kernel implements Plugin
{
    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
    ) {}

    public function getPluginKey(): string
    {
        return 'dishes';
    }

    public function getMenuLinks(): array
    {
        return [
            new Link(slug: $this->urlGenerator->generate('app_plugin_dishes'), name: 'Dishes'),
        ];
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

    public function getEventListItemTags(int $eventId): array
    {
        return [];
    }

    public function warmCache(WarmCacheType $type, array $ids): void
    {
    }

    public function getMemberPageTop(): ?string
    {
        return null;
    }

    public function getFooterLinks(string $column): array
    {
        return [];
    }

    public function getFooterColumnTitle(string $column): ?string
    {
        return null;
    }
}
