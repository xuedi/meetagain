<?php declare(strict_types=1);

namespace Plugin\Boardgames\Item;

use App\Entity\EventItemAssociation;
use App\Enum\ItemViewType;
use App\Item\ListCellProviderInterface;
use App\Item\ListProviderInterface;
use App\Item\TypeProviderInterface;
use Override;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Service\GameService;
use Plugin\Boardgames\Service\ShelfService;
use Twig\Environment;

final readonly class GameTypeProvider implements TypeProviderInterface, ListCellProviderInterface, ListProviderInterface
{
    public function __construct(
        private GameService $gameService,
        private ShelfService $shelfService,
        private Environment $twig,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'boardgames';
    }

    #[Override]
    public function getKey(): string
    {
        return GameService::ITEM_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'boardgames.item_label';
    }

    #[Override]
    public function renderEventCell(int $itemId, EventItemAssociation $association): ?string
    {
        $game = $this->gameService->getAttached($itemId);
        if ($game === null) {
            return null;
        }

        return $this->twig->render('@Boardgames/item/event_cell.html.twig', [
            'game' => $game,
            'association' => $association,
        ]);
    }

    #[Override]
    public function renderListCell(int $itemId, ?ItemViewType $mode = null): ?string
    {
        $game = $this->gameService->get($itemId);
        if ($game === null) {
            return null;
        }

        return $this->twig->render('@Boardgames/item/list_cell.html.twig', [
            'game' => $game,
            'viewMode' => $mode?->value,
            'owners' => $this->shelfService->getPublicOwners($game),
        ]);
    }

    #[Override]
    public function getItemIds(): array
    {
        return array_values(array_map(static fn(Game $game): int => (int) $game->getId(), $this->gameService->getList()));
    }

    #[Override]
    public function renderList(): string
    {
        $itemIds = $this->getItemIds();
        $this->shelfService->warmPublicOwners($itemIds);

        return $this->twig->render('@Boardgames/item/list_body.html.twig', [
            'itemIds' => $itemIds,
        ]);
    }

    #[Override]
    public function getListRoute(): string
    {
        return 'app_boardgames_gamelist';
    }

    #[Override]
    public function getDetailRoute(): ?string
    {
        return 'app_plugin_boardgames_game_show';
    }

    #[Override]
    public function isDetailIndexable(): bool
    {
        return true;
    }

    #[Override]
    public function getLastmodByItemId(array $itemIds): array
    {
        $wanted = array_flip($itemIds);

        $stamps = [];
        foreach ($this->gameService->getList() as $game) {
            $id = (int) $game->getId();
            $createdAt = $game->getCreatedAt();
            if ($createdAt === null || !isset($wanted[$id])) {
                continue;
            }

            $stamps[$id] = $createdAt;
        }

        return $stamps;
    }

    #[Override]
    public function renderAttachPicker(int $eventId): string
    {
        return $this->twig->render('@Boardgames/item/attach_picker.html.twig', [
            'eventId' => $eventId,
            'games' => $this->gameService->getManagedList(),
        ]);
    }

    #[Override]
    public function getPriority(): int
    {
        return 25;
    }
}
