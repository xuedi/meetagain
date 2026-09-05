<?php declare(strict_types=1);

namespace Plugin\Boardgames\Cron;

use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\ValueObject\CronTaskResult;
use DateTimeImmutable;
use Override;
use Plugin\Boardgames\Entity\BringRequest;
use Plugin\Boardgames\Repository\BringRequestRepository;
use Plugin\Boardgames\Repository\GameOwnershipRepository;
use Plugin\Boardgames\Service\BringRequestService;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

final readonly class MaintenanceTask implements CronTaskInterface
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
    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        try {
            $expired = $this->expireStartedEvents();
            $swept = $this->sweepStaleRequests();

            $message = sprintf('%d requests expired, %d stale requests swept', $expired, $swept);
            $output->writeln('BoardgamesMaintenance: ' . $message);

            return new CronTaskResult(self::IDENTIFIER, CronTaskStatus::ok, $message);
        } catch (Throwable $exception) {
            $output->writeln('BoardgamesMaintenance exception: ' . $exception->getMessage());

            return new CronTaskResult(self::IDENTIFIER, CronTaskStatus::exception, $exception->getMessage());
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
