<?php declare(strict_types=1);

namespace Plugin\Voting\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Voting\Entity\Vote;

/**
 * @extends ServiceEntityRepository<Vote>
 */
class VoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Vote::class);
    }

    /** @return Vote[] */
    public function findByPollAndUser(int $pollId, int $userId): array
    {
        return $this->findBy(['poll' => $pollId, 'userId' => $userId]);
    }

    public function deleteByPollAndUser(int $pollId, int $userId): void
    {
        $this
            ->createQueryBuilder('v')
            ->delete()
            ->where('v.poll = :pollId AND v.userId = :userId')
            ->setParameter('pollId', $pollId)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }

    public function hasUserVoted(int $pollId, int $userId): bool
    {
        return $this->count(['poll' => $pollId, 'userId' => $userId]) > 0;
    }

    /**
     * @return array<int, int> item_id => vote_count
     */
    public function countVotesPerItem(int $pollId): array
    {
        $rows = $this
            ->createQueryBuilder('v')
            ->select('v.itemId AS item_id, COUNT(v.id) AS vote_count')
            ->where('v.poll = :pollId')
            ->setParameter('pollId', $pollId)
            ->groupBy('v.itemId')
            ->getQuery()
            ->getArrayResult();

        $result = [];
        foreach ($rows as $row) {
            $result[(int) $row['item_id']] = (int) $row['vote_count'];
        }

        return $result;
    }
}
