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

    /** @return Book[] */
    public function findApproved(?array $allowedBookIds = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.approved = true')
            ->orderBy('b.title', 'ASC');

        if ($allowedBookIds !== null) {
            if ($allowedBookIds === []) {
                return [];
            }
            $qb->andWhere('b.id IN (:ids)')->setParameter('ids', $allowedBookIds);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Book[] */
    public function findPending(?array $allowedBookIds = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->where('b.approved = false')
            ->orderBy('b.createdAt', 'DESC');

        if ($allowedBookIds !== null) {
            if ($allowedBookIds === []) {
                return [];
            }
            $qb->andWhere('b.id IN (:ids)')->setParameter('ids', $allowedBookIds);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return Book[] */
    public function findAllBooks(?array $allowedBookIds = null): array
    {
        $qb = $this->createQueryBuilder('b')
            ->orderBy('b.title', 'ASC');

        if ($allowedBookIds !== null) {
            if ($allowedBookIds === []) {
                return [];
            }
            $qb->where('b.id IN (:ids)')->setParameter('ids', $allowedBookIds);
        }

        return $qb->getQuery()->getResult();
    }
}
