<?php declare(strict_types=1);

namespace Plugin\Filmclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Filmclub\Entity\Vote;

/**
 * @extends ServiceEntityRepository<Vote>
 */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    public function save(Vote $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function findByEventId(int $eventId): ?Vote
    {
        return $this->findOneBy(['eventId' => $eventId]);
    }

    /**
     * @return Vote[]
     */
    public function findOpenVotes(): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.isClosed = false')
            ->andWhere('v.closesAt > :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('v.closesAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return Vote[]
     */
    public function findClosedVotes(): array
    {
        return $this->createQueryBuilder('v')
            ->where('v.isClosed = true OR v.closesAt <= :now')
            ->setParameter('now', new \DateTimeImmutable())
            ->orderBy('v.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
