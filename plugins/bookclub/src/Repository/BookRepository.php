<?php declare(strict_types=1);

namespace Plugin\Bookclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Bookclub\Entity\Book;

/**
 * @extends ServiceEntityRepository<Book>
 */
class BookRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Book::class);
    }

    public function findByIsbn(string $isbn): ?Book
    {
        return $this->findOneBy(['isbn' => $isbn]);
    }
}
