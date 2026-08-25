<?php declare(strict_types=1);

namespace Module\Trust\Internal\Repository;

use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Module\Trust\Contract\TrustLevel;
use Module\Trust\Internal\Entity\TrustGrant;

/**
 * @extends ServiceEntityRepository<TrustGrant>
 */
class TrustGrantRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TrustGrant::class);
    }

    /**
     * @return list<array{from: int, to: int, level: TrustLevel}>
     */
    public function findEdges(string $context): array
    {
        $rows = $this->createQueryBuilder('g')
            ->select('IDENTITY(g.fromUser) AS fromUser', 'IDENTITY(g.toUser) AS toUser', 'g.level')
            ->where('g.context = :context')
            ->setParameter('context', $context)
            ->getQuery()
            ->getArrayResult();

        $edges = [];
        foreach ($rows as $row) {
            $edges[] = ['from' => (int) $row['fromUser'], 'to' => (int) $row['toUser'], 'level' => $row['level']];
        }

        return $edges;
    }

    public function findEdge(string $context, int $fromUserId, int $toUserId): ?TrustGrant
    {
        return $this->createQueryBuilder('g')
            ->where('g.context = :context')
            ->andWhere('IDENTITY(g.fromUser) = :from')
            ->andWhere('IDENTITY(g.toUser) = :to')
            ->setParameter('context', $context)
            ->setParameter('from', $fromUserId)
            ->setParameter('to', $toUserId)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return array<int, TrustLevel>
     */
    public function findOutgoing(string $context, int $fromUserId): array
    {
        $rows = $this->createQueryBuilder('g')
            ->select('IDENTITY(g.toUser) AS toUser', 'g.level')
            ->where('g.context = :context')
            ->andWhere('IDENTITY(g.fromUser) = :from')
            ->setParameter('context', $context)
            ->setParameter('from', $fromUserId)
            ->getQuery()
            ->getArrayResult();

        $outgoing = [];
        foreach ($rows as $row) {
            $outgoing[(int) $row['toUser']] = $row['level'];
        }

        return $outgoing;
    }

    /**
     * @return array<int, int>
     */
    public function countIncomingByUser(string $context): array
    {
        $rows = $this->createQueryBuilder('g')
            ->select('IDENTITY(g.toUser) AS toUser', 'COUNT(g.id) AS total')
            ->where('g.context = :context')
            ->setParameter('context', $context)
            ->groupBy('g.toUser')
            ->getQuery()
            ->getArrayResult();

        $counts = [];
        foreach ($rows as $row) {
            $counts[(int) $row['toUser']] = (int) $row['total'];
        }

        return $counts;
    }

    public function findRevision(string $context): ?string
    {
        $row = $this->createQueryBuilder('g')
            ->select('COUNT(g.id) AS total', 'MAX(g.updatedAt) AS latest')
            ->where('g.context = :context')
            ->setParameter('context', $context)
            ->getQuery()
            ->getSingleResult();

        if ((int) $row['total'] === 0) {
            return null;
        }

        return $row['total'] . ':' . $row['latest'];
    }

    /**
     * @return list<string>
     */
    public function findContexts(): array
    {
        $rows = $this->createQueryBuilder('g')
            ->select('DISTINCT g.context AS context')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn(array $row): string => (string) $row['context'], $rows);
    }
}
