<?php declare(strict_types=1);

namespace Plugin\Films\Item;

use App\Entity\EventItemAssociation;
use App\Item\TypeProviderInterface;
use App\Item\ListCellProviderInterface;
use Override;
use Plugin\Films\Service\FilmService;
use Twig\Environment;

final readonly class FilmTypeProvider implements TypeProviderInterface, ListCellProviderInterface
{
    public function __construct(
        private FilmService $filmService,
        private Environment $twig,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'films';
    }

    #[Override]
    public function getKey(): string
    {
        return FilmService::ITEM_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'films.item_label';
    }

    #[Override]
    public function renderEventCell(int $itemId, EventItemAssociation $association): ?string
    {
        $film = $this->filmService->get($itemId);
        if ($film === null) {
            return null;
        }

        return $this->twig->render('@Films/item/event_cell.html.twig', [
            'film' => $film,
            'association' => $association,
        ]);
    }

    #[Override]
    public function renderListCell(int $itemId): ?string
    {
        $film = $this->filmService->get($itemId);
        if ($film === null) {
            return null;
        }

        return $this->twig->render('@Films/item/list_cell.html.twig', [
            'film' => $film,
        ]);
    }

    #[Override]
    public function renderAttachPicker(int $eventId): string
    {
        return $this->twig->render('@Films/item/attach_picker.html.twig', [
            'eventId' => $eventId,
            'films' => $this->filmService->getList(),
        ]);
    }

    #[Override]
    public function getPriority(): int
    {
        return 10;
    }
}
