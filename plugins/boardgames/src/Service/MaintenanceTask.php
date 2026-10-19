<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use DateTimeImmutable;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Override;
use Plugin\Boardgames\Entity\BringRequest;
use Plugin\Boardgames\Repository\BringRequestRepository;
use Plugin\Boardgames\Repository\GameOwnershipRepository;
use Throwable;

final readonly class MaintenanceTask implements TaskInterface
{
    public const string IDENTIFIER = 'boardgames-maintenance';

    public function __construct(
        private BringRequestRepository $requests,
        private GameOwnershipRepository $ownerships,
        private BringRequestService $requestService,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return self::IDENTIFIER;
    }

    #[Override]
    public function getCadence(): Cadence
    {
        return new Cadence('25 * * * *');
    }

    #[Override]
    public function getDescription(): string
    {
        return 'Tidies up requests to bring a game to an event. Hourly, open requests whose event has started expire, and so do requests whose owner, game or event no longer exists or whose owner no longer offers the game to be asked for.';
    }

    #[Override]
    public function run(): Result
    {
        try {
            $expired = $this->expireStartedEvents();
            $swept = $this->sweepStaleRequests();

            $message = sprintf('%d requests expired, %d stale requests swept', $expired, $swept);

            return new Result(Status::ok, $message);
        } catch (Throwable $exception) {
            return new Result(Status::exception, $exception->getMessage());
        }
    }

    private function expireStartedEvents(): int
    {
        $expired = 0;
        foreach ($this->requests->findOpenStartingBefore(new DateTimeImmutable()) as $request) {
            $this->requestService->expire($request);
            $expired++;
        }

        return $expired;
    }

    private function sweepStaleRequests(): int
    {
        $swept = 0;
        foreach ($this->requests->findAllOpen() as $request) {
            if (!$this->isStale($request)) {
                continue;
            }

            $this->requestService->expire($request);
            $swept++;
        }

        return $swept;
    }

    private function isStale(BringRequest $request): bool
    {
        $owner = $request->getOwnerUser();
        $game = $request->getGame();
        if ($owner === null || $game === null || $request->getEvent() === null) {
            return true;
        }

        $ownership = $this->ownerships->findOneFor($owner, $game);

        return $ownership === null || !$ownership->isAskable();
    }
}
