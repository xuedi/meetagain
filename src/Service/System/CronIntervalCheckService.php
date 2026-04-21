<?php declare(strict_types=1);

namespace App\Service\System;

use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\Repository\CronLogRepository;
use App\ValueObject\CronTaskResult;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Output\OutputInterface;

readonly class CronIntervalCheckService implements CronTaskInterface
{
    // Budget: 5 min base + slack for slow tasks (e.g. email delivery API).
    // <= 10 min ok, 10-20 min warning, > 20 min error.
    private const int WARNING_THRESHOLD_SECONDS = 600;
    private const int ERROR_THRESHOLD_SECONDS = 1200;

    public function __construct(
        private CronLogRepository $cronLogRepository,
        private ClockInterface $clock,
    ) {}

    public function getIdentifier(): string
    {
        return 'cron-interval-check';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        $previous = $this->cronLogRepository->findMostRecent();
        if ($previous === null) {
            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, 'first run');
        }

        $gapSeconds = $this->clock->now()->getTimestamp() - $previous->getRunAt()->getTimestamp();

        if ($gapSeconds > self::ERROR_THRESHOLD_SECONDS) {
            $message = sprintf(
                'late cron: previous run was %ds ago (error threshold: %ds)',
                $gapSeconds,
                self::ERROR_THRESHOLD_SECONDS,
            );
            $output->writeln('CronIntervalCheckService: ' . $message);

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::error, $message);
        }

        if ($gapSeconds > self::WARNING_THRESHOLD_SECONDS) {
            $message = sprintf(
                'late cron: previous run was %ds ago (warning threshold: %ds)',
                $gapSeconds,
                self::WARNING_THRESHOLD_SECONDS,
            );
            $output->writeln('CronIntervalCheckService: ' . $message);

            return new CronTaskResult($this->getIdentifier(), CronTaskStatus::warning, $message);
        }

        return new CronTaskResult($this->getIdentifier(), CronTaskStatus::ok, sprintf('gap: %ds', $gapSeconds));
    }
}
