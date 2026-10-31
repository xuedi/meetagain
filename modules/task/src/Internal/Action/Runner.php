<?php declare(strict_types=1);

namespace Module\Task\Internal\Action;

use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Internal\Entity\ActionRequest;
use Module\Task\Internal\Entity\Run;
use Module\Task\Internal\Message\RunAction;
use Module\Task\Internal\Repository\ActionRequestRepository;
use Module\Task\Internal\Repository\RunRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
readonly class Runner
{
    public const int LEASE_SECONDS = 3600;
    public const string LOCK_PREFIX = 'task-action-';

    public function __construct(
        private Registry $registry,
        private ActionRequestRepository $requests,
        private RunRepository $runs,
        private LockFactory $locks,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function __invoke(RunAction $message): void
    {
        $this->handle($message->requestId);
    }

    public function handle(int $requestId): void
    {
        $request = $this->requests->find($requestId);
        if ($request === null || $request->getState() !== RequestState::Pending) {
            return;
        }

        $lock = $this->locks->createLock(self::LOCK_PREFIX . $request->getIdentifier(), self::LEASE_SECONDS, autoRelease: false);
        if (!$lock->acquire()) {
            return;
        }

        try {
            $this->requests->refresh($request);
            if ($request->getState() === RequestState::Pending) {
                $this->execute($request);
            }
        } finally {
            $lock->release();
        }
    }

    public function abandon(ActionRequest $request): void
    {
        $this->complete($request, new Result(Status::error, 'The worker stopped before the action finished. Queue it again if it should still run.'), 0);
    }

    private function execute(ActionRequest $request): void
    {
        $now = $this->clock->now();
        $request->start($now, $now->modify(sprintf('+%d seconds', self::LEASE_SECONDS)));
        $this->requests->save($request);

        $action = $this->registry->find($request->getIdentifier());
        $start = (int) hrtime(true);
        if ($action === null) {
            $result = new Result(Status::error, sprintf('No action is registered as "%s".', $request->getIdentifier()));
        } else {
            try {
                $result = $action->run($request->getValues());
            } catch (Throwable $exception) {
                $this->logger->error('Action {identifier} failed: {message}', [
                    'identifier' => $request->getIdentifier(),
                    'message' => $exception->getMessage(),
                    'exception' => $exception,
                ]);
                $result = new Result(Status::exception, $exception->getMessage());
            }
        }

        $this->complete($request, $result, intdiv((int) hrtime(true) - $start, 1_000_000));
    }

    private function complete(ActionRequest $request, Result $result, int $durationMs): void
    {
        $now = $this->clock->now();
        $request->finish($result, $now);
        $this->requests->save($request);
        $this->runs->record(
            new Run($request->getIdentifier(), $request->getStartedAt() ?? $now, $durationMs, $result->status, $result->message, (string) gethostname()),
        );
    }
}
