<?php declare(strict_types=1);

namespace Module\Email\Internal;

use Module\Email\Internal\Repository\EmailQueueRepository;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Symfony\Component\Clock\ClockInterface;

final readonly class Retention implements TaskInterface
{
    public const int EMAIL_BODY_RETENTION_DAYS = 30;
    public const int EMAIL_ROW_RETENTION_DAYS = 365;

    public function __construct(
        private EmailQueueRepository $repo,
        private ClockInterface $clock,
    ) {}

    public function getIdentifier(): string
    {
        return 'email-retention';
    }

    public function getCadence(): Cadence
    {
        return new Cadence('45 3 * * *', jitterSeconds: 1800, offloaded: true);
    }

    public function getDescription(): string
    {
        return sprintf(
            'Keeps the send log from holding rendered mails for ever. Once a night it removes the rendered body and the template context - the links, names and addresses a mail was built from - of every sent mail older than %d days, keeping its recipient, subject, template and status, and deletes every sent, late or failed mail older than %d days. A mail still waiting to be sent is never touched.',
            self::EMAIL_BODY_RETENTION_DAYS,
            self::EMAIL_ROW_RETENTION_DAYS,
        );
    }

    public function run(): Result
    {
        $now = $this->clock->now();
        $cleared = $this->repo->clearSentBodiesBefore($now->modify(sprintf('-%d days', self::EMAIL_BODY_RETENTION_DAYS)));
        $deleted = $this->repo->deleteFinishedBefore($now->modify(sprintf('-%d days', self::EMAIL_ROW_RETENTION_DAYS)));

        return new Result(Status::ok, sprintf('bodies_cleared: %d, rows_deleted: %d', $cleared, $deleted));
    }
}
