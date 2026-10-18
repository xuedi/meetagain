<?php declare(strict_types=1);

namespace Module\Task\Internal;

use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Module\Task\Internal\Repository\RunRepository;
use Symfony\Component\Clock\ClockInterface;

readonly class RunRetention implements TaskInterface
{
    public const int KEEP_DAYS = 30;

    public function __construct(
        private RunRepository $runRepository,
        private ClockInterface $clock,
    ) {}

    public function getIdentifier(): string
    {
        return 'task.run-retention';
    }

    public function getCadence(): Cadence
    {
        return new Cadence('15 3 * * *', jitterSeconds: 1800);
    }

    public function getDescription(): string
    {
        return sprintf(
            'Keeps the task run history from growing without bound. Once a night it deletes every recorded run that started more than %d days ago - the history shown on these pages and used by the duration graph.',
            self::KEEP_DAYS,
        );
    }

    public function run(): Result
    {
        $deleted = $this->runRepository->deleteStartedBefore($this->clock->now()->modify(sprintf('-%d days', self::KEEP_DAYS)));

        return new Result(Status::ok, sprintf('%d runs deleted', $deleted));
    }
}
