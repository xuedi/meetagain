<?php declare(strict_types=1);

namespace Module\Ballot\Internal\Cron;

use DateTimeImmutable;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\SettlementMode;
use Module\Ballot\Internal\Entity\Ballot;
use Module\Ballot\Internal\Repository\BallotRepository;
use Module\Task\Contract\Cadence;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Contract\TaskInterface;
use Override;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class SettleDueBallotsCron implements TaskInterface
{
    public function __construct(
        private BallotRepository $ballots,
        private BallotInterface $ballotService,
        private LoggerInterface $logger,
    ) {}

    #[Override]
    public function getIdentifier(): string
    {
        return 'ballot.settle-due';
    }

    #[Override]
    public function getCadence(): Cadence
    {
        return new Cadence('* * * * *');
    }

    #[Override]
    public function run(): Result
    {
        $settled = 0;
        $waiting = 0;
        $errors = 0;

        foreach ($this->ballots->findDue(new DateTimeImmutable('now')) as $ballot) {
            try {
                if ($this->process($ballot)) {
                    $settled++;
                    continue;
                }
                $waiting++;
            } catch (Throwable $e) {
                $errors++;
                $this->logger->error('SettleDueBallotsCron: failed to settle ballot', [
                    'ballot_id' => $ballot->getId(),
                    'error' => $e->getMessage(),
                ]);
            }
        }

        return new Result($errors > 0 ? Status::error : Status::ok, sprintf('%d settled, %d left for a person, %d errors', $settled, $waiting, $errors));
    }

    private function process(Ballot $ballot): bool
    {
        $ballotId = (int) $ballot->getId();
        $outcome = $this->ballotService->tally($ballotId);

        if ($ballot->getSettlementMode() !== SettlementMode::Automatic || $outcome->winningKey === null) {
            return false;
        }

        $this->ballotService->settle($ballotId, $outcome->winningKey);

        return true;
    }
}
