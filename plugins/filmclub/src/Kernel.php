<?php declare(strict_types=1);

namespace Plugin\Filmclub;

use App\Entity\Link;
use App\Plugin;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {

    }
    public function getPluginKey(): string
    {
        return 'filmclub';
    }

    public function getMenuLinks(): array
    {
        return [
            new Link(
                slug: $this->urlGenerator->generate('app_filmclub_filmlist'),
                name: 'Filme',
            )
        ];
    }

    public function getEventTile(int $eventId): ?string
    {
        return "rendered tile";
    }
}
