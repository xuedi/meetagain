<?php declare(strict_types=1);

namespace Plugin\Bookclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Bookclub\Entity\Book;
use Plugin\Bookclub\Entity\BookNote;

/**
 * @extends ServiceEntityRepository<BookNote>
 */
class BookNoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookNote::class);
    }

    public function findUserNote(Book $book, int $userId): ?BookNote
    {
        return $this->findOneBy([
            'book' => $book,
            'userId' => $userId,
        ]);
    }

    /** @return BookNote[] */
    public function findUserNotes(int $userId): array
    {
        return $this->findBy(
            ['userId' => $userId],
            ['updatedAt' => 'DESC', 'createdAt' => 'DESC']
        );
    }
}
