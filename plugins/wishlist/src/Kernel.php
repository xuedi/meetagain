<?php declare(strict_types=1);

namespace Plugin\Wishlist;

use App\Entity\Link;
use App\Enum\EventTileLocation;
use App\Enum\WarmCacheType;
use App\Plugin;
use App\Repository\EventItemAssociationRepository;
use App\Repository\UserRepository;
use App\ValueObject\LinkCollection;
use Plugin\Wishlist\Service\WishlistService;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class Kernel implements Plugin
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly WishlistService $wishlistService,
        private readonly EventItemAssociationRepository $associations,
        private readonly UserRepository $userRepository,
    ) {}

    public function getPluginKey(): string
    {
        return 'wishlist';
    }

    public function getLinkCollection(): LinkCollection
    {
        return LinkCollection::empty()->withNavLinks([
            new Link(slug: $this->urlGenerator->generate('app_wishlist_mine'), name: 'wishlist.menu_main'),
        ]);
    }

    public function getEventTile(int $eventId, EventTileLocation $location): ?string
    {
        return null;
    }

    public function loadPostExtendFixtures(OutputInterface $output): void
    {
        // Wishlist is item-agnostic: seed a backlog over whichever item type has the most
        // events-attached items, read from the neutral core association table.
        [$itemType, $itemIds] = $this->richestSeededItemType();
        if ($itemIds === []) {
            $output->writeln('<comment>Wishlist: no seeded items to add, skipping.</comment>');

            return;
        }

        $userIds = $this->wanterIds(6);
        if ($userIds === []) {
            $output->writeln('<comment>Wishlist: no users found, skipping.</comment>');

            return;
        }

        if ($this->wishlistService->hasEntries()) {
            $output->writeln('<comment>Wishlist: already seeded, skipping.</comment>');

            return;
        }

        $count = 0;
        foreach ($userIds as $offset => $userId) {
            // Each member wants a rotating window of items, so wanter counts differ per item.
            for ($n = 0; $n < 3; $n++) {
                $itemId = $itemIds[($offset + $n) % count($itemIds)];
                $this->wishlistService->add($itemType, $itemId, $userId);
                $count++;
            }
        }

        $output->writeln(sprintf('<info>Wishlist: seeded %d %s backlog entries.</info>', $count, $itemType));
    }

    /**
     * @return array{0: string, 1: list<int>} the item type with the most events-attached items, and its ids
     */
    private function richestSeededItemType(): array
    {
        $best = ['film', []];
        foreach (['film', 'book', 'dish'] as $itemType) {
            $ids = $this->associations->findItemIdsByType($itemType);
            if (count($ids) > count($best[1])) {
                $best = [$itemType, $ids];
            }
        }

        return $best;
    }

    /** @return list<int> */
    private function wanterIds(int $limit): array
    {
        $ids = [];
        foreach ($this->userRepository->findAll() as $user) {
            $ids[] = (int) $user->getId();
            if (count($ids) >= $limit) {
                break;
            }
        }

        return $ids;
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
