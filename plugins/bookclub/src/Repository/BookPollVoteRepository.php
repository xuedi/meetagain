<?php declare(strict_types=1);

namespace Plugin\Bookclub\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Bookclub\Entity\BookPoll;
use Plugin\Bookclub\Entity\BookPollVote;

/**
 * @extends ServiceEntityRepository<BookPollVote>
 */
class BookPollVoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BookPollVote::class);
    }

    public function findUserVote(BookPoll $poll, int $userId): ?BookPollVote
    {
        return $this->findOneBy([
            'poll' => $poll,
            'userId' => $userId,
        ]);
    }

    /**
     * @return array<int, int> Array of suggestion_id => vote_count
     */
    public function getVoteCounts(BookPoll $poll): array
    {
        $qb = $this->createQueryBuilder('v')
            ->select('IDENTITY(v.suggestion) as suggestionId, COUNT(v.id) as voteCount')
            ->where('v.poll = :poll')
            ->setParameter('poll', $poll)
            ->groupBy('v.suggestion');

        $results = $qb->getQuery()->getResult();

        $counts = [];
        foreach ($results as $row) {
            $counts[(int) $row['suggestionId']] = (int) $row['voteCount'];
        }

        return $counts;
    }
}
