<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\CommandExecutionLog;
use App\Entity\CommandExecutionStatus;
use App\Repository\CommandExecutionLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;

readonly class CommandExecutionService
{
    public function __construct(
        private EntityManagerInterface $em,
        private CommandExecutionLogRepository $repo,
    ) {
    }

    public function start(string $commandName, string $triggeredBy = 'cron'): CommandExecutionLog
    {
        $log = new CommandExecutionLog();
        $log->setCommandName($commandName);
        $log->setStartedAt(new DateTimeImmutable());
        $log->setStatus(CommandExecutionStatus::Running);
        $log->setTriggeredBy($triggeredBy);

        $this->em->persist($log);
        $this->em->flush();

        return $log;
    }

    public function complete(CommandExecutionLog $log, int $exitCode, ?string $output = null, ?string $errorOutput = null): void
    {
        $log->setCompletedAt(new DateTimeImmutable());
        $log->setExitCode($exitCode);
        $log->setOutput($output);
        $log->setErrorOutput($errorOutput);

        if ($exitCode === 0) {
            $log->setStatus(CommandExecutionStatus::Success);
        } else {
            $log->setStatus(CommandExecutionStatus::Failed);
        }

        $this->em->flush();
    }

    public function fail(CommandExecutionLog $log, ?string $errorOutput = null): void
    {
        $log->setCompletedAt(new DateTimeImmutable());
        $log->setStatus(CommandExecutionStatus::Failed);
        $log->setExitCode(1);
        $log->setErrorOutput($errorOutput);

        $this->em->flush();
    }

    public function timeout(CommandExecutionLog $log): void
    {
        $log->setCompletedAt(new DateTimeImmutable());
        $log->setStatus(CommandExecutionStatus::Timeout);
        $log->setExitCode(124);

        $this->em->flush();
    }

    /**
     * Get stats for dashboard.
     *
     * @return array{total: int, successful: int, failed: int}
     */
    public function getStats(int $hours = 24): array
    {
        $since = new DateTimeImmutable("-{$hours} hours");

        return $this->repo->getStats($since);
    }

    /**
     * Get last execution for each command.
     *
     * @return array<string, CommandExecutionLog>
     */
    public function getLastExecutions(): array
    {
        return $this->repo->getLastExecutionsByCommand();
    }

    /**
     * Get recent failed executions.
     *
     * @return CommandExecutionLog[]
     */
    public function getRecentFailed(int $limit = 10): array
    {
        return $this->repo->getRecentFailed($limit);
    }
}
