<?php declare(strict_types=1);

namespace App\Repository;

use App\Entity\CommandExecutionLog;
use App\Enum\CommandExecutionStatus;
use DateTimeImmutable;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CommandExecutionLog>
 */
class CommandExecutionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CommandExecutionLog::class);
    }

    /**
     * @return array<string, CommandExecutionLog>
     */
    public function getLastExecutionsByCommand(): array
    {
        $logs = $this->createQueryBuilder('c')->orderBy('c.startedAt', 'DESC')->getQuery()->getResult();

        $result = [];
        foreach ($logs as $log) {
            /** @var CommandExecutionLog $log */
            $name = $log->getCommandName();
            if ($name !== null && !isset($result[$name])) {
                $result[$name] = $log;
            }
        }

        return $result;
    }

    /**
     * @return array{total: int, successful: int, failed: int}
     */
    public function getStats(DateTimeImmutable $since): array
    {
        $result = $this
            ->createQueryBuilder('c')
            ->select(
                'COUNT(c.id) as total',
                'SUM(CASE WHEN c.status = :success THEN 1 ELSE 0 END) as successful',
                'SUM(CASE WHEN c.status IN (:failed, :timeout) THEN 1 ELSE 0 END) as failed',
            )
            ->where('c.startedAt > :since')
            ->setParameter('since', $since)
            ->setParameter('success', CommandExecutionStatus::Success)
            ->setParameter('failed', CommandExecutionStatus::Failed)
            ->setParameter('timeout', CommandExecutionStatus::Timeout)
            ->getQuery()
            ->getSingleResult();

        return [
            'total' => (int) $result['total'],
            'successful' => (int) $result['successful'],
            'failed' => (int) $result['failed'],
        ];
    }

    /**
     * @return CommandExecutionLog[]
     */
    public function getRecentFailed(int $limit = 10): array
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.status IN (:statuses)')
            ->setParameter('statuses', [CommandExecutionStatus::Failed, CommandExecutionStatus::Timeout])
            ->orderBy('c.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    /**
     * @return CommandExecutionLog[]
     */
    public function getHistoryForCommand(string $commandName, int $limit = 20): array
    {
        return $this
            ->createQueryBuilder('c')
            ->where('c.commandName = :name')
            ->setParameter('name', $commandName)
            ->orderBy('c.startedAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function isCommandRunning(string $commandName): bool
    {
        $count = $this
            ->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->where('c.commandName = :name')
            ->andWhere('c.status = :status')
            ->setParameter('name', $commandName)
            ->setParameter('status', CommandExecutionStatus::Running)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
