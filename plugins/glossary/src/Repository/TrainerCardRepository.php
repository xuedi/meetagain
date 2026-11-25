<?php declare(strict_types=1);

namespace Plugin\Glossary\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Plugin\Glossary\Entity\TrainerCard;
use Plugin\Glossary\Enum\CardState;
use Plugin\Glossary\Enum\Direction;
use SortDirection;

/**
 * @extends ServiceEntityRepository<TrainerCard>
 */
class TrainerCardRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrainerCard::class);
    }

    public function findOneFor(int $userId, int $glossaryId, Direction $direction): ?TrainerCard
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.userId = :userId')
            ->andWhere('IDENTITY(c.glossary) = :glossaryId')
            ->andWhere('c.direction = :direction')
            ->setParameter('userId', $userId)
            ->setParameter('glossaryId', $glossaryId)
            ->setParameter('direction', $direction)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /** @return list<TrainerCard> */
    public function findForEntry(int $userId, int $glossaryId): array
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.userId = :userId')
            ->andWhere('IDENTITY(c.glossary) = :glossaryId')
            ->setParameter('userId', $userId)
            ->setParameter('glossaryId', $glossaryId)
            ->orderBy('c.direction', SortDirection::Ascending)
            ->getQuery()
            ->getResult();
    }

    /**
     * @param list<int> $glossaryIds
     * @return list<int> earliest due first; each entry once even when due in several directions
     */
    public function dueGlossaryIds(int $userId, ?Direction $direction, array $glossaryIds, DateTimeImmutable $now): array
    {
        if ($glossaryIds === []) {
            return [];
        }

        $qb = $this
            ->scheduled($userId, $glossaryIds)
            ->select('IDENTITY(c.glossary) AS glossaryId')
            ->andWhere('c.dueAt <= :now')
            ->setParameter('now', $now)
            ->orderBy('c.dueAt', SortDirection::Ascending);
        if ($direction !== null) {
            $qb->andWhere('c.direction = :direction')->setParameter('direction', $direction);
        }

        return array_values(array_unique(array_map(intval(...), array_column($qb->getQuery()->getScalarResult(), 'glossaryId'))));
    }

    /**
     * @param list<int> $glossaryIds
     * @return list<int>
     */
    public function scheduledGlossaryIds(int $userId, Direction $direction, array $glossaryIds): array
    {
        if ($glossaryIds === []) {
            return [];
        }

        $rows = $this
            ->createQueryBuilder('c')
            ->select('IDENTITY(c.glossary) AS glossaryId')
            ->where('c.userId = :userId')
            ->andWhere('c.direction = :direction')
            ->andWhere('c.state != :new')
            ->andWhere('c.glossary IN (:glossaryIds)')
            ->setParameter('userId', $userId)
            ->setParameter('direction', $direction)
            ->setParameter('new', CardState::New)
            ->setParameter('glossaryIds', $glossaryIds)
            ->getQuery()
            ->getScalarResult();

        return array_map(intval(...), array_column($rows, 'glossaryId'));
    }

    /** @return list<int> */
    public function suspendedGlossaryIds(int $userId): array
    {
        return $this->glossaryIdsWhere($userId, 'c.suspended = true');
    }

    /** @return list<int> */
    public function markedGlossaryIds(int $userId): array
    {
        return $this->glossaryIdsWhere($userId, 'c.marked = true');
    }

    /** @return list<int> */
    public function seenGlossaryIds(int $userId): array
    {
        return $this->glossaryIdsWhere($userId, 'c.timesSeen > 0');
    }

    /**
     * @param list<int> $glossaryIds
     * @return list<int> entries the member got wrong at least once, worst miss rate first
     */
    public function worstGlossaryIds(int $userId, array $glossaryIds, int $limit): array
    {
        if ($glossaryIds === []) {
            return [];
        }

        $rows = $this
            ->createQueryBuilder('c')
            ->select('IDENTITY(c.glossary) AS glossaryId')
            ->addSelect('SUM(c.timesSeen - c.timesCorrect) * 1000 / SUM(c.timesSeen) AS HIDDEN missRate')
            ->where('c.userId = :userId')
            ->andWhere('c.glossary IN (:glossaryIds)')
            ->andWhere('c.timesSeen > 0')
            ->setParameter('userId', $userId)
            ->setParameter('glossaryIds', $glossaryIds)
            ->groupBy('c.glossary')
            ->having('SUM(c.timesSeen - c.timesCorrect) > 0')
            ->orderBy('missRate', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        return array_map(intval(...), array_column($rows, 'glossaryId'));
    }

    /** @return array{learners: int, lapsed: int} */
    public function lapseCounts(int $glossaryId): array
    {
        $row = $this
            ->createQueryBuilder('c')
            ->select('COUNT(DISTINCT c.userId) AS learners')
            ->addSelect('COUNT(DISTINCT CASE WHEN c.state = :relearning THEN c.userId ELSE :none END) AS lapsed')
            ->where('IDENTITY(c.glossary) = :glossaryId')
            ->andWhere('c.timesSeen > 0')
            ->setParameter('glossaryId', $glossaryId)
            ->setParameter('relearning', CardState::Relearning)
            ->setParameter('none', null)
            ->getQuery()
            ->getSingleResult();

        return ['learners' => (int) $row['learners'], 'lapsed' => (int) $row['lapsed']];
    }

    /**
     * @param list<int> $glossaryIds
     * @return array<int, int> user id => answers given on these entries, most first
     */
    public function answerCountsByUser(array $glossaryIds, int $limit): array
    {
        if ($glossaryIds === []) {
            return [];
        }

        $rows = $this
            ->createQueryBuilder('c')
            ->select('c.userId AS userId', 'SUM(c.timesSeen) AS answers')
            ->where('c.glossary IN (:glossaryIds)')
            ->setParameter('glossaryIds', $glossaryIds)
            ->groupBy('c.userId')
            ->having('SUM(c.timesSeen) > 0')
            ->orderBy('answers', SortDirection::Descending)
            ->setMaxResults($limit)
            ->getQuery()
            ->getScalarResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['userId']] = (int) $row['answers'];
        }

        return $counts;
    }

    public function deleteForUser(int $userId): void
    {
        $this
            ->createQueryBuilder('c')
            ->delete()
            ->where('c.userId = :userId')
            ->setParameter('userId', $userId)
            ->getQuery()
            ->execute();
    }

    /** @param list<int> $glossaryIds */
    private function scheduled(int $userId, array $glossaryIds): QueryBuilder
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.userId = :userId')
            ->andWhere('c.suspended = false')
            ->andWhere('c.state != :new')
            ->andWhere('c.glossary IN (:glossaryIds)')
            ->setParameter('userId', $userId)
            ->setParameter('new', CardState::New)
            ->setParameter('glossaryIds', $glossaryIds);
    }

    /** @return list<int> */
    private function glossaryIdsWhere(int $userId, string $condition): array
    {
        $rows = $this
            ->createQueryBuilder('c')
            ->select('DISTINCT IDENTITY(c.glossary) AS glossaryId')
            ->where('c.userId = :userId')
            ->andWhere($condition)
            ->setParameter('userId', $userId)
            ->getQuery()
            ->getScalarResult();

        return array_map(intval(...), array_column($rows, 'glossaryId'));
    }
}
