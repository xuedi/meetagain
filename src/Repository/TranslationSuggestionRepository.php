<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\TranslationSuggestion;
use App\Entity\TranslationSuggestionStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TranslationSuggestion>
 */
class TranslationSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TranslationSuggestion::class);
    }

    public function getPendingCount(): int
    {
        return (int) $this->createQueryBuilder('ts')
            ->select('COUNT(ts.id)')
            ->where('ts.status = :status')
            ->setParameter('status', TranslationSuggestionStatus::Requested)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return TranslationSuggestion[]
     */
    public function getPending(int $limit = 10): array
    {
        return $this->createQueryBuilder('ts')
            ->where('ts.status = :status')
            ->setParameter('status', TranslationSuggestionStatus::Requested)
            ->orderBy('ts.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
