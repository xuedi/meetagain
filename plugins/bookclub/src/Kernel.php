<?php declare(strict_types=1);

namespace Plugin\Bookclub;

use App\Entity\AdminSection;
use App\Entity\Link;
use App\Entity\WarmCacheType;
use App\Plugin;
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

    public function getMenuLinks(): array
    {
        return [
            new Link(slug: $this->urlGenerator->generate('app_plugin_bookclub'), name: 'books'),
            new Link(slug: $this->urlGenerator->generate('app_plugin_bookclub_poll_list'), name: 'polls'),
        ];
    }

    public function getEventTile(int $eventId): ?string
    {
        $selection = $this->selectionRepository->findByEventId($eventId);
        $poll = $this->pollRepository->findByEventId($eventId);
        $activePoll = ($poll !== null && $poll->getStatus() === PollStatus::Active) ? $poll : null;

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
}
