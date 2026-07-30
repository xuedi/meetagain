<?php declare(strict_types=1);

namespace Plugin\Books\Item;

use App\Entity\EventItemAssociation;
use App\Item\TypeProviderInterface;
use App\Item\ListCellProviderInterface;
use App\Item\ListProviderInterface;
use Override;
use Plugin\Books\Entity\Book;
use Plugin\Books\Service\BookService;
use Twig\Environment;

final readonly class BookTypeProvider implements TypeProviderInterface, ListCellProviderInterface, ListProviderInterface
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
        $book = $this->bookService->getAttached($itemId);
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
    public function getItemIds(): array
    {
        return array_values(array_map(static fn(Book $book): int => (int) $book->getId(), $this->bookService->getList()));
    }

    #[Override]
    public function renderList(): string
    {
        return $this->twig->render('@Books/item/list_body.html.twig', [
            'itemIds' => $this->getItemIds(),
        ]);
    }

    #[Override]
    public function getListRoute(): string
    {
        return 'app_books_booklist';
    }

    #[Override]
    public function getDetailRoute(): ?string
    {
        return 'app_plugin_books_book_show';
    }

    #[Override]
    public function getLastmodByItemId(array $itemIds): array
    {
        $wanted = array_flip($itemIds);

        $stamps = [];
        foreach ($this->bookService->getList() as $book) {
            $id = (int) $book->getId();
            $createdAt = $book->getCreatedAt();
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
        return $this->twig->render('@Books/item/attach_picker.html.twig', [
            'eventId' => $eventId,
            'books' => $this->bookService->getManagedList(),
        ]);
    }

    #[Override]
    public function getPriority(): int
    {
        return 20;
    }
}
