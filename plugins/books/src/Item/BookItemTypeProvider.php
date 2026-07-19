<?php declare(strict_types=1);

namespace Plugin\Books\Item;

use App\Entity\EventItemAssociation;
use App\Item\ItemTypeProviderInterface;
use Override;
use Plugin\Books\Service\BookService;
use Twig\Environment;

/**
 * Registers the 'book' item type with the core item seam: the event-detail cell, the shared
 * list cell, and the steward attach picker are all rendered from the books plugin's templates.
 */
final readonly class BookItemTypeProvider implements ItemTypeProviderInterface
{
    public function __construct(
        private BookService $bookService,
        private Environment $twig,
    ) {}

    #[Override]
    public function getPluginKey(): string
    {
        return 'books';
    }

    #[Override]
    public function getKey(): string
    {
        return BookService::ITEM_TYPE;
    }

    #[Override]
    public function getLabelKey(): string
    {
        return 'books.item_label';
    }

    #[Override]
    public function renderEventCell(int $itemId, EventItemAssociation $association): ?string
    {
        $book = $this->bookService->get($itemId);
        if ($book === null) {
            return null;
        }

        return $this->twig->render('@Books/item/event_cell.html.twig', [
            'book' => $book,
            'association' => $association,
        ]);
    }

    #[Override]
    public function renderListCell(int $itemId): ?string
    {
        $book = $this->bookService->get($itemId);
        if ($book === null) {
            return null;
        }

        return $this->twig->render('@Books/item/list_cell.html.twig', [
            'book' => $book,
        ]);
    }

    #[Override]
    public function renderAttachPicker(int $eventId): string
    {
        return $this->twig->render('@Books/item/attach_picker.html.twig', [
            'eventId' => $eventId,
            'books' => $this->bookService->getList(),
        ]);
    }

    #[Override]
    public function getPriority(): int
    {
        return 20;
    }
}
