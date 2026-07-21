<?php declare(strict_types=1);

namespace Plugin\Dishes\Item;

use App\Entity\EventItemAssociation;
use App\Item\ItemTypeProviderInterface;
use App\Item\ListCellProviderInterface;
use Override;
use Plugin\Dishes\Service\DishService;
use Twig\Environment;

/**
 * Registers the 'dish' item type with the core item seams: event attachment plus the shared list
 * cell. The event-detail cell surfaces the association's sectionLabel (the former dinner-course
 * name), so an event's dish associations - ordered by position - read as a menu grouped by course.
 */
final readonly class DishItemTypeProvider implements ItemTypeProviderInterface, ListCellProviderInterface
{
    public function __construct(
        private DishService $dishService,
        private Environment $twig,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'dishes';
    }

    #[Override]
    public function getKey(): string
    {
        return DishService::ITEM_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'dishes.item_label';
    }

    #[Override]
    public function renderEventCell(int $itemId, EventItemAssociation $association): ?string
    {
        $dish = $this->dishService->get($itemId);
        if ($dish === null) {
            return null;
        }

        return $this->twig->render('@Dishes/item/event_cell.html.twig', [
            'dish' => $dish,
            'association' => $association,
            'sectionLabel' => $association->getSectionLabel(),
        ]);
    }

    #[Override]
    public function renderListCell(int $itemId): ?string
    {
        $dish = $this->dishService->get($itemId);
        if ($dish === null) {
            return null;
        }

        return $this->twig->render('@Dishes/item/list_cell.html.twig', [
            'dish' => $dish,
        ]);
    }

    #[Override]
    public function renderAttachPicker(int $eventId): string
    {
        return $this->twig->render('@Dishes/item/attach_picker.html.twig', [
            'eventId' => $eventId,
            'dishes' => $this->dishService->getList(),
        ]);
    }

    #[Override]
    public function getPriority(): int
    {
        return 30;
    }
}
