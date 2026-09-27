<?php declare(strict_types=1);

namespace Module\Email\Internal\Repository;

use DateTime;
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
            ->setParameter('threshold', new DateTime('-' . $minutes . ' minutes'))
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
}
