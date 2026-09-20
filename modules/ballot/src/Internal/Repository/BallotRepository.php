<?php declare(strict_types=1);

namespace Module\Ballot\Internal\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\Ballot\Contract\BallotStatus;
use Module\Ballot\Internal\Entity\Ballot;

/**
 * @extends ServiceEntityRepository<Ballot>
 */
class BallotRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Ballot::class);
    }

    /**
     * @return list<Ballot>
     */
    public function findOpen(): array
    {
        return $this
            ->createQueryBuilder('b')
            ->where('b.status = :status')
            ->setParameter('status', BallotStatus::Open)
            ->orderBy('b.deadline', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Ballot>
     */
    public function findDue(DateTimeImmutable $now): array
    {
        return $this
            ->createQueryBuilder('b')
            ->where('b.status = :status')
            ->andWhere('b.deadline <= :now')
            ->setParameter('status', BallotStatus::Open)
            ->setParameter('now', $now)
            ->orderBy('b.deadline', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Ballot>
     */
    public function findForPurpose(string $purpose): array
    {
        return $this
            ->createQueryBuilder('b')
            ->where('b.purpose = :purpose')
            ->setParameter('purpose', $purpose)
            ->orderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<Ballot>
     */
    public function findForSubject(string $subjectType, int $subjectId): array
    {
        return $this
            ->createQueryBuilder('b')
            ->where('b.subjectType = :type')
            ->andWhere('b.subjectId = :id')
            ->setParameter('type', $subjectType)
            ->setParameter('id', $subjectId)
            ->orderBy('b.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
