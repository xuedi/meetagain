<?php declare(strict_types=1);

namespace Plugin\Filmclub;

use App\Entity\Link;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\ValueObject\LinkCollection;
use Plugin\Filmclub\Filter\FilmGroupFilterService;
use Plugin\Filmclub\Repository\FilmPollRepository;
use Plugin\Filmclub\Repository\FilmSelectionRepository;
use Plugin\Filmclub\Repository\FilmWishlistEntryRepository;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;
use Twig\Environment;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly Environment $twig,
        private readonly FilmSelectionRepository $selectionRepo,
        private readonly FilmPollRepository $pollRepo,
        private readonly FilmWishlistEntryRepository $wishlistRepo,
        private readonly FilmGroupFilterService $groupFilter,
        private readonly TokenStorageInterface $tokenStorage,
    ) {}

    public function getPluginKey(): string
    {
        return 'filmclub';
    }

    public function getLinkCollection(): LinkCollection
    {
        return LinkCollection::empty()->withNavLinks([
            new Link(slug: $this->urlGenerator->generate('app_plugin_filmclub_landing'), name: 'filmclub.menu_main'),
        ]);
    }

    public function getEventTile(int $eventId, EventTileLocation $location): ?string
    {
        if ($location !== EventTileLocation::Sidebar) {
            return null;
        }

        $selection = $this->selectionRepo->findByEvent($eventId);
        $activePoll = $this->pollRepo->findActiveForEvent($eventId, $this->groupFilter->getAllowedPollIds());

        $isWishlisted = false;
        $canCreatePoll = false;
        $wishlistPoolCount = 0;
        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();

        if ($user !== null && method_exists($user, 'getId')) {
            if ($selection !== null) {
                $isWishlisted = $this->wishlistRepo->findByUserAndFilm(
                    $user->getId(),
                    $selection->getFilm()->getId(),
                ) !== null;
            }

            if (method_exists($user, 'getRoles')) {
                $roles = $user->getRoles();
                $canCreatePoll = in_array('ROLE_ORGANIZER', $roles, true)
                    || in_array('ROLE_STEWARD', $roles, true);
            }

            if ($canCreatePoll) {
                $wishlistPoolCount = count(
                    $this->wishlistRepo->aggregateByFilm($this->groupFilter->getAllowedWishlistEntryIds()),
                );
            }
        }

        return $this->twig->render('@Filmclub/tile/event.html.twig', [
            'eventId' => $eventId,
            'selection' => $selection,
            'activePoll' => $activePoll,
            'isWishlisted' => $isWishlisted,
            'canCreatePoll' => $canCreatePoll,
            'wishlistPoolCount' => $wishlistPoolCount,
        ]);
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
        $output->writeln('<comment>Filmclub: fixture support will be added in a later phase.</comment>');
    }

    public function preFixtures(OutputInterface $output): void {}

    public function postFixtures(OutputInterface $output): void {}

    public function getFooterAbout(): ?string
    {
        return null;
    }

    public function getEventListItemTags(int $eventId): array
    {
        return [];
    }

    public function warmCache(WarmCacheType $type, array $ids): void {}

    public function getStylesheets(): array
    {
        return [];
    }

    public function getJavascripts(): array
    {
        return [];
    }
}
