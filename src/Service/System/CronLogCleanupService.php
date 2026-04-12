<?php declare(strict_types=1);

namespace App\Service\System;

use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\Repository\CronLogRepository;
use App\ValueObject\CronTaskResult;
use DateTimeImmutable;
use Symfony\Component\Console\Output\OutputInterface;

readonly class CronLogCleanupService implements CronTaskInterface
{
    public function __construct(
        private CronLogRepository $cronLogRepository,
    ) {}

    public function getIdentifier(): string
    {
        return 'cron-log-cleanup';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        try {
            $cutoff = new DateTimeImmutable('-7 days');
            $deleted = $this->cronLogRepository->deleteOlderThan($cutoff);
            $message = sprintf('%d deleted', $deleted);
            $output->writeln('CronLogCleanupService: ' . $message);

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, $message);
        } catch (\Throwable $e) {
            $output->writeln('CronLogCleanupService exception: ' . $e->getMessage());

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::exception, $e->getMessage());
        }
    }
}
