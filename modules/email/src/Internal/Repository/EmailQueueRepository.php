<?php declare(strict_types=1);

namespace Module\Email\Internal\Repository;

use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;
use Module\Email\Contract\QueueStatus;
use Module\Email\Internal\Entity\EmailQueue;

/**
 * @extends ServiceEntityRepository<EmailQueue>
 */
class EmailQueueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, EmailQueue::class);
    }

    /**
     * @param list<string> $urgentTemplates template identifiers claimed before every other row
     * @return list<EmailQueue>
     */
    public function claimPending(int $limit, DateTimeImmutable $now, DateTimeImmutable $leaseExpiredBefore, array $urgentTemplates = []): array
    {
        return $this->claim($this->findClaimableIds($limit, $leaseExpiredBefore, $urgentTemplates), $now, $leaseExpiredBefore);
    }

    /**
     * @param list<string> $urgentTemplates template identifiers claimed before every other row
     * @return list<int>
     */
    public function findClaimableIds(int $limit, DateTimeImmutable $leaseExpiredBefore, array $urgentTemplates = []): array
    {
        $query = $this->claimableQuery($leaseExpiredBefore)->select('eq.id');
        if ($urgentTemplates !== []) {
            $query
                ->addSelect('CASE WHEN eq.template IN (:urgent) THEN 0 ELSE 1 END AS HIDDEN urgency')
                ->setParameter('urgent', $urgentTemplates)
                ->orderBy('urgency', 'ASC');
        }

        return array_map(intval(...), $query->addOrderBy('eq.id', 'ASC')->setMaxResults($limit)->getQuery()->getSingleColumnResult());
    }

    /**
     * @param list<int> $candidates
     * @return list<EmailQueue>
     */
    public function claim(array $candidates, DateTimeImmutable $now, DateTimeImmutable $leaseExpiredBefore): array
    {
        if ($candidates === []) {
            return [];
        }

        $token = bin2hex(random_bytes(16));
        $this
            ->claimableQuery($leaseExpiredBefore)
            ->update()
            ->set('eq.claimToken', ':token')
            ->set('eq.claimedAt', ':now')
            ->andWhere('eq.id IN (:candidates)')
            ->setParameter('token', $token)
            ->setParameter('now', $now)
            ->setParameter('candidates', $candidates)
            ->getQuery()
            ->execute();

        return array_values($this->findBy(['claimToken' => $token], ['id' => 'ASC']));
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('eq')->select('COUNT(eq.id)')->getQuery()->getSingleScalarResult();
    }

    /**
     * @param list<QueueStatus>|null $statuses
     * @return EmailQueue[]
     */
    public function findFiltered(
        int $limit,
        ?DateTimeImmutable $since = null,
        ?string $template = null,
        ?string $recipient = null,
        ?array $statuses = null,
    ): array {
        $qb = $this->createQueryBuilder('eq')->orderBy('eq.createdAt', 'DESC')->addOrderBy('eq.id', 'DESC')->setMaxResults($limit);
        $this->applyFilters($qb, $since, $template, $recipient, $statuses);

        return $qb->getQuery()->getResult();
    }

    /**
     * @param list<QueueStatus>|null $statuses
     */
    public function countFiltered(?DateTimeImmutable $since = null, ?string $template = null, ?string $recipient = null, ?array $statuses = null): int
    {
        $qb = $this->createQueryBuilder('eq')->select('COUNT(eq.id)');
        $this->applyFilters($qb, $since, $template, $recipient, $statuses);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function countCreatedBetween(DateTimeImmutable $from, DateTimeImmutable $to): int
    {
        return (int) $this
            ->createQueryBuilder('eq')
            ->select('COUNT(eq.id)')
            ->where('eq.createdAt >= :from')
            ->andWhere('eq.createdAt <= :to')
            ->setParameter('from', $from)
            ->setParameter('to', $to)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getPendingCount(): int
    {
        return (int) $this
            ->createQueryBuilder('eq')
            ->select('COUNT(eq.id)')
            ->where('eq.status = :status')
            ->setParameter('status', QueueStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getStaleCount(int $minutes = 60): int
    {
        return (int) $this
            ->createQueryBuilder('eq')
            ->select('COUNT(eq.id)')
            ->where('eq.status = :status')
            ->andWhere('eq.createdAt < :threshold')
            ->setParameter('status', QueueStatus::Pending)
            ->setParameter('threshold', new DateTimeImmutable('-' . $minutes . ' minutes'))
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOldestPendingCreatedAt(): ?DateTimeImmutable
    {
        $oldest = $this
            ->createQueryBuilder('eq')
            ->select('MIN(eq.createdAt)')
            ->where('eq.status = :status')
            ->setParameter('status', QueueStatus::Pending)
            ->getQuery()
            ->getSingleScalarResult();

        return is_string($oldest) ? new DateTimeImmutable($oldest) : null;
    }

    /**
     * @return array{total: int, sent: int, failed: int}
     */
    public function getDeliveryStats(DateTimeImmutable $since): array
    {
        $result = $this
            ->createQueryBuilder('eq')
            ->select(
                'COUNT(eq.id) as total',
                'SUM(CASE WHEN eq.status = :sent THEN 1 ELSE 0 END) as sent',
                'SUM(CASE WHEN eq.status = :failed THEN 1 ELSE 0 END) as failed',
            )
            ->where('eq.providerDispatchedAt IS NOT NULL OR eq.status = :failed')
            ->andWhere('eq.createdAt > :since')
            ->setParameter('sent', QueueStatus::Sent)
            ->setParameter('failed', QueueStatus::Failed)
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'sent' => (int) $result['sent'],
            'failed' => (int) $result['failed'],
        ];
    }

    /**
     * @return \Module\Email\Internal\Entity\EmailQueue[]
     */
    public function findWithProviderMessageIdAndNoStatus(int $limit): array
    {
        return $this
            ->createQueryBuilder('eq')
            ->where('eq.providerMessageId IS NOT NULL')
            ->andWhere('eq.providerStatus IS NULL')
            ->orderBy('eq.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function clearSentBodiesBefore(DateTimeImmutable $cutoff): int
    {
        return (int) $this
            ->createQueryBuilder('eq')
            ->update()
            ->set('eq.renderedBody', 'NULL')
            ->set('eq.context', ':empty')
            ->where('eq.status = :sent')
            ->andWhere('eq.createdAt < :cutoff')
            ->andWhere('eq.renderedBody IS NOT NULL')
            ->setParameter('empty', '[]')
            ->setParameter('sent', QueueStatus::Sent)
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }

    public function deleteFinishedBefore(DateTimeImmutable $cutoff): int
    {
        return (int) $this
            ->createQueryBuilder('eq')
            ->delete()
            ->where('eq.status IN (:finished)')
            ->andWhere('eq.createdAt < :cutoff')
            ->setParameter('finished', [QueueStatus::Sent, QueueStatus::Late, QueueStatus::Failed])
            ->setParameter('cutoff', $cutoff)
            ->getQuery()
            ->execute();
    }

    public function deletePendingFor(string $address): int
    {
        $qb = $this->createQueryBuilder('eq')->delete()->where('eq.status = :pending')->setParameter('pending', QueueStatus::Pending);
        $this->matchRecipient($qb, $address);

        return (int) $qb->getQuery()->execute();
    }

    public function anonymiseRecipient(string $address, string $replacement): int
    {
        $qb = $this
            ->createQueryBuilder('eq')
            ->update()
            ->set('eq.recipient', ':replacement')
            ->set('eq.renderedBody', 'NULL')
            ->set('eq.attachments', 'NULL')
            ->set('eq.context', ':empty')
            ->setParameter('replacement', $replacement)
            ->setParameter('empty', '[]');
        $this->matchRecipient($qb, $address);

        return (int) $qb->getQuery()->execute();
    }

    private function matchRecipient(QueryBuilder $qb, string $address): void
    {
        $qb
            ->andWhere('LOWER(eq.recipient) = :recipient OR LOWER(eq.recipient) LIKE :namedRecipient')
            ->setParameter('recipient', mb_strtolower($address))
            ->setParameter('namedRecipient', '%<' . addcslashes(mb_strtolower($address), '%_') . '>');
    }

    /**
     * @param list<QueueStatus>|null $statuses
     */
    private function applyFilters(QueryBuilder $qb, ?DateTimeImmutable $since, ?string $template, ?string $recipient, ?array $statuses): void
    {
        if ($since !== null) {
            $qb->andWhere('eq.createdAt >= :since')->setParameter('since', $since);
        }
        if ($template !== null) {
            $qb->andWhere('eq.template = :template')->setParameter('template', $template);
        }
        if ($recipient !== null && $recipient !== '') {
            $qb
                ->andWhere('eq.recipient = :recipient OR eq.recipient LIKE :namedRecipient')
                ->setParameter('recipient', $recipient)
                ->setParameter('namedRecipient', '%<' . addcslashes($recipient, '%_') . '>');
        }
        if ($statuses !== null && $statuses !== []) {
            $qb->andWhere('eq.status IN (:statuses)')->setParameter('statuses', $statuses);
        }
    }

    private function claimableQuery(DateTimeImmutable $leaseExpiredBefore): QueryBuilder
    {
        return $this
            ->createQueryBuilder('eq')
            ->where('eq.status = :pending')
            ->andWhere('(eq.claimedAt IS NULL OR eq.claimedAt < :expired)')
            ->setParameter('pending', QueueStatus::Pending)
            ->setParameter('expired', $leaseExpiredBefore);
    }
}
