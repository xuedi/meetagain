<?php declare(strict_types=1);

namespace Module\Task\Internal\Action;

use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Module\Task\Internal\Repository\ActionRequestRepository;
use Override;
use Symfony\Component\Clock\ClockInterface;

readonly class Drain implements TaskInterface
{
    public const int GRACE_SECONDS = 120;

    public function __construct(
        private ActionRequestRepository $requests,
        private Runner $runner,
        private ClockInterface $clock,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return 'action-drain';
    }

    #[Override]
    public function getCadence(): Cadence
    {
        return new Cadence('*/10 * * * *', offloaded: true);
    }

    #[Override]
    public function getDescription(): string
    {
        return 'The backstop for the Maintenance page. A request whose worker died mid-run is marked as failed and not run again, because not every action can safely run twice; the admin queues it again. A request still waiting a few minutes after it was submitted lost its message and is run here, one action at a time.';
    }

    #[Override]
    public function run(): Result
    {
        $now = $this->clock->now();
        $abandoned = $this->requests->findLeaseExpired($now);
        foreach ($abandoned as $request) {
            $this->runner->abandon($request);
        }

        $pending = $this->requests->findPendingIdsCreatedBefore($now->modify(sprintf('-%d seconds', self::GRACE_SECONDS)));
        foreach ($pending as $requestId) {
            $this->runner->handle($requestId);
        }

        return new Result(
            $abandoned === [] ? Status::ok : Status::warning,
            sprintf('%d abandoned request(s) marked failed, %d waiting request(s) picked up', count($abandoned), count($pending)),
        );
    }
}
