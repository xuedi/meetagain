<?php declare(strict_types=1);

namespace Module\Task\Internal\Action;

use Module\Task\Contract\ActionInterface;
use Module\Task\Internal\Entity\ActionRequest;
use Module\Task\Internal\Message\RunAction;
use Module\Task\Internal\Repository\ActionRequestRepository;
use Module\Task\Internal\ScheduleProvider;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\TransportNamesStamp;

readonly class Requests
{
    public function __construct(
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
