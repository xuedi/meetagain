<?php declare(strict_types=1);

namespace Plugin\Bookclub;

use App\Entity\Link;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\ValueObject\LinkCollection;
use Plugin\Bookclub\Entity\PollStatus;
use Plugin\Bookclub\Repository\BookPollRepository;
use Plugin\Bookclub\Repository\BookSelectionRepository;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Environment;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        private readonly BookSelectionRepository $selectionRepository,
        private readonly BookPollRepository $pollRepository,
    ) {}

    public function getPluginKey(): string
    {
        return 'bookclub';
    }

    public function getLinkCollection(): LinkCollection
    {
        return LinkCollection::empty()->withNavLinks([
            new Link(slug: $this->urlGenerator->generate('app_plugin_bookclub'), name: 'books'),
        ]);
    }

    public function getEventTile(int $eventId, EventTileLocation $location): ?string
    {
        $selection = $this->selectionRepository->findByEventId($eventId);
        $poll = $this->pollRepository->findByEventId($eventId);
        $activePoll = $poll !== null && $poll->getStatus() === PollStatus::Active ? $poll : null;

        return $this->twig->render('@Bookclub/tile/event.html.twig', [
            'selection' => $selection,
            'activePoll' => $activePoll,
            'eventId' => $eventId,
        ]);
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
