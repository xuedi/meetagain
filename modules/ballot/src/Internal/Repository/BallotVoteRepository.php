<?php declare(strict_types=1);

namespace Module\Ballot\Internal\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\Ballot\Internal\Entity\Ballot;
use Module\Ballot\Internal\Entity\BallotVote;

/**
 * @extends ServiceEntityRepository<BallotVote>
 */
class BallotVoteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, BallotVote::class);
    }

    /**
     * @return array<string, int>
     */
    public function countByOptionKey(int $ballotId): array
    {
        $rows = $this
            ->createQueryBuilder('v')
            ->select('v.optionKey AS optionKey', 'COUNT(v.id) AS total')
            ->where('IDENTITY(v.ballot) = :ballot')
            ->setParameter('ballot', $ballotId)
            ->groupBy('v.optionKey')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(string) $row['optionKey']] = (int) $row['total'];
        }

        return $counts;
    }

    public function countVoters(int $ballotId): int
    {
        return (int) $this
            ->createQueryBuilder('v')
            ->select('COUNT(DISTINCT IDENTITY(v.user))')
            ->where('IDENTITY(v.ballot) = :ballot')
            ->setParameter('ballot', $ballotId)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<string>
     */
    public function findSelection(int $ballotId, int $userId): array
    {
        $rows = $this
            ->createQueryBuilder('v')
            ->select('v.optionKey AS optionKey')
            ->where('IDENTITY(v.ballot) = :ballot')
            ->andWhere('IDENTITY(v.user) = :user')
            ->setParameter('ballot', $ballotId)
            ->setParameter('user', $userId)
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn(array $row): string => (string) $row['optionKey'], $rows);
    }

    /**
     * @return list<BallotVote>
     */
    public function findForVoter(Ballot $ballot, int $userId): array
    {
        return $this
            ->createQueryBuilder('v')
            ->where('v.ballot = :ballot')
            ->andWhere('IDENTITY(v.user) = :user')
            ->setParameter('ballot', $ballot)
            ->setParameter('user', $userId)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return array<int, array<int, list<string>>>
     */
    public function findAllSelections(): array
    {
        $rows = $this
            ->createQueryBuilder('v')
            ->select('IDENTITY(v.ballot) AS ballotId', 'IDENTITY(v.user) AS userId', 'v.optionKey AS optionKey')
            ->orderBy('v.id', 'ASC')
            ->getQuery()
            ->getArrayResult();

        $selections = [];
        foreach ($rows as $row) {
            $selections[(int) $row['ballotId']][(int) $row['userId']][] = (string) $row['optionKey'];
        }

        return $selections;
    }
}
