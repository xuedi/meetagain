<?php declare(strict_types=1);

namespace Plugin\Dishes;

use App\Entity\Link;
use App\Plugin;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Kernel implements Plugin
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function getPluginKey(): string
    {
        return 'dishes';
    }

    public function getMenuLinks(): array
    {
        return [
            new Link(
                slug: $this->urlGenerator->generate('app_plugin_dishes'),
                name: 'Dishes',
            )
        ];
    }

    public function getEventTile(int $eventId): ?string
    {
        return null;
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
    }
}
