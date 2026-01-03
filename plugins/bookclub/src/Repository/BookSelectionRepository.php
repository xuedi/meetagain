<?php declare(strict_types=1);

namespace Plugin\Bookclub\Repository;

use App\Entity\Event;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Bookclub\Entity\Book;
use Plugin\Bookclub\Entity\BookSelection;

/**
 * @extends ServiceEntityRepository<BookSelection>
 */
class BookSelectionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookSelection::class);
    }

    public function findByEvent(Event $event): ?BookSelection
    {
        return $this->findOneBy(['event' => $event]);
    }

    /** @return BookSelection[] */
    public function findByBook(Book $book): array
    {
        return $this->findBy(['book' => $book], ['selectedAt' => 'DESC']);
    }
}
