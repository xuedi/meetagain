<?php declare(strict_types=1);

namespace Plugin\Filmclub\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Filmclub\Entity\FilmSuggestion;
use Plugin\Filmclub\Entity\SuggestionStatus;

/**
 * @extends ServiceEntityRepository<FilmSuggestion>
 */
class FilmSuggestionRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FilmSuggestion::class);
    }

    public function save(FilmSuggestion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FilmSuggestion $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return FilmSuggestion[] */
    public function findAllPending(?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('s')
            ->where('s.status = :status')
            ->setParameter('status', SuggestionStatus::Pending)
            ->orderBy('s.suggestedAt', 'ASC');

        if ($allowedIds !== null) {
            $qb->andWhere('s.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }

    /** @return FilmSuggestion[] */
    public function findUserPending(int $userId, ?array $allowedIds = null): array
    {
        if ($allowedIds === []) {
            return [];
        }

        $qb = $this->createQueryBuilder('s')
            ->where('s.suggestedBy = :userId AND s.status = :status')
            ->setParameter('userId', $userId)
            ->setParameter('status', SuggestionStatus::Pending)
            ->orderBy('s.suggestedAt', 'DESC');

        if ($allowedIds !== null) {
            $qb->andWhere('s.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        return $qb->getQuery()->getResult();
    }

    public function getLatestPendingAt(?array $allowedIds = null): ?DateTimeImmutable
    {
        if ($allowedIds === []) {
            return null;
        }

        $qb = $this->createQueryBuilder('s')
            ->select('MAX(s.suggestedAt)')
            ->where('s.status = :status')
            ->setParameter('status', SuggestionStatus::Pending);

        if ($allowedIds !== null) {
            $qb->andWhere('s.id IN (:ids)')->setParameter('ids', $allowedIds);
        }

        $result = $qb->getQuery()->getSingleScalarResult();

        return $result !== null ? new DateTimeImmutable($result) : null;
    }
}
