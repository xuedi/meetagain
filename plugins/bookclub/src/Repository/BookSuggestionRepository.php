<?php declare(strict_types=1);

namespace Plugin\Bookclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Bookclub\Entity\BookSuggestion;
use Plugin\Bookclub\Entity\SuggestionStatus;

/**
 * @extends ServiceEntityRepository<BookSuggestion>
 */
class BookSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookSuggestion::class);
    }

    /**
     * @param int[]|null $allowedIds null = no restriction
     * @return BookSuggestion[]
     */
    public function findUserPendingSuggestions(int $userId, ?array $allowedIds = null): array
    {
        $qb = $this
            ->createQueryBuilder('s')
            ->where('s.suggestedBy = :userId')
            ->andWhere('s.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', SuggestionStatus::Pending);

        if ($allowedIds !== null) {
            if ($allowedIds === []) {
                return [];
            }
            $qb->andWhere('s.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * @param int[]|null $allowedIds null = no restriction
     * @return BookSuggestion[]
     */
    public function findAllPending(?array $allowedIds = null): array
    {
        $qb = $this
            ->createQueryBuilder('s')
            ->where('s.status = :status')
            ->setParameter('status', SuggestionStatus::Pending)
            ->orderBy('s.suggestedAt', 'ASC');

        if ($allowedIds !== null) {
            if ($allowedIds === []) {
                return [];
            }
            $qb->andWhere('s.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }
}
