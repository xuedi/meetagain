<?php declare(strict_types=1);

namespace Module\Task\Internal\Action;

use InvalidArgumentException;
use Module\Task\Contract\ActionInterface;
use Module\Task\Contract\ActionQueueInterface;
use Module\Task\Contract\RequestedAction;
use Module\Task\Internal\Entity\ActionRequest;
use Module\Task\Internal\Message\RunAction;
use Module\Task\Internal\Repository\ActionRequestRepository;
use Module\Task\Internal\ScheduleProvider;
use Override;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

readonly class Requests implements ActionQueueInterface
{
    public function __construct(
        private Registry $registry,
        private ActionRequestRepository $repository,
        private MessageBusInterface $bus,
        private ClockInterface $clock,
    ) {}

    /**
     * @param array<string, string|int|bool|null> $values
     */
    public function submit(ActionInterface $action, array $values, ?string $requestedBy): ActionRequest
    {
        $request = new ActionRequest($action->getIdentifier(), $values, $requestedBy, $this->clock->now());
        $this->repository->save($request);
        $this->ring($request);

        return $request;
    }

    #[Override]
    public function queue(string $identifier, array $values, ?string $requestedBy): void
    {
        $action = $this->registry->find($identifier) ?? throw new InvalidArgumentException(sprintf('No action "%s".', $identifier));
        $this->submit($action, $values, $requestedBy);
    }

    #[Override]
    public function latest(string $identifier): ?RequestedAction
    {
        $request = $this->repository->findRecent($identifier, 1)[0] ?? null;
        if ($request === null) {
            return null;
        }

        return new RequestedAction(
            (int) $request->getId(),
            $request->getIdentifier(),
            $request->getCreatedAt(),
            $request->getStartedAt(),
            $request->getFinishedAt(),
            $request->getStatus(),
            $request->getMessage(),
        );
    }

    public function ring(ActionRequest $request): void
    {
        $this->bus->dispatch(new RunAction((int) $request->getId()), [new TransportNamesStamp([ScheduleProvider::OFFLOAD_TRANSPORT])]);
    }

    /**
     * @return array<string, ActionRequest> the newest request per action
     */
    public function latestPerAction(): array
    {
        return $this->repository->findLatestPerIdentifier();
    }

    /**
     * @return list<ActionRequest> newest first
     */
    public function recent(string $identifier, int $limit): array
    {
        return $this->repository->findRecent($identifier, $limit);
    }
}
