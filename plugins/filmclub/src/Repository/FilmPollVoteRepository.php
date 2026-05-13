<?php declare(strict_types=1);

namespace Plugin\Filmclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Filmclub\Entity\FilmPollVote;

/**
 * @extends ServiceEntityRepository<FilmPollVote>
 */
class FilmPollVoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FilmPollVote::class);
    }

    public function save(FilmPollVote $entity, bool $flush = false): void
    {
        $this->getEntityManager()->persist($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    public function remove(FilmPollVote $entity, bool $flush = false): void
    {
        $this->getEntityManager()->remove($entity);

        if ($flush) {
            $this->getEntityManager()->flush();
        }
    }

    /** @return FilmPollVote[] */
    public function findByPollAndUser(int $pollId, int $userId): array
    {
        return $this->findBy(['poll' => $pollId, 'userId' => $userId]);
    }

    public function countByPoll(int $pollId): int
    {
        return (int) $this->createQueryBuilder('v')
            ->select('COUNT(DISTINCT v.userId)')
            ->where('v.poll = :pollId')
            ->setParameter('pollId', $pollId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteByPollAndUser(int $pollId, int $userId): void
    {
        $this->createQueryBuilder('v')
            ->delete()
            ->where('v.poll = :pollId AND v.userId = :userId')
            ->setParameter('pollId', $pollId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }
}
