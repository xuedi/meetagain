<?php declare(strict_types=1);

namespace Plugin\Bookclub\Service;

use App\Entity\Event;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Bookclub\Entity\Book;
use Plugin\Bookclub\Entity\BookSelection;
use Plugin\Bookclub\Repository\BookSelectionRepository;
use RuntimeException;

readonly class SelectionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private BookSelectionRepository $selectionRepo,
    ) {}

    public function select(Book $book, Event $event, int $userId): BookSelection
    {
        $existing = $this->selectionRepo->findByEvent($event);
        if ($existing !== null) {
            throw new RuntimeException('This event already has a book selected');
        }

        $selection = new BookSelection();
        $selection->setBook($book);
        $selection->setEvent($event);
        $selection->setSelectedBy($userId);
        $selection->setSelectedAt(new DateTimeImmutable());

        $this->em->persist($selection);
        $this->em->flush();

        return $selection;
    }

    public function getByEvent(Event $event): ?BookSelection
    {
        return $this->selectionRepo->findByEvent($event);
    }

    /** @return BookSelection[] */
    public function getByBook(Book $book): array
    {
        return $this->selectionRepo->findByBook($book);
    }

    public function remove(int $selectionId): void
    {
        $selection = $this->selectionRepo->find($selectionId);
        if ($selection !== null) {
            $this->em->remove($selection);
            $this->em->flush();
        }
    }

    public function get(int $id): ?BookSelection
    {
        return $this->selectionRepo->find($id);
    }
}
