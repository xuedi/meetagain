<?php declare(strict_types=1);

namespace Plugin\Filmclub\Cron;

use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\ValueObject\CronTaskResult;
use Plugin\Filmclub\Repository\FilmPollRepository;
use Plugin\Filmclub\Service\PollService;
use Psr\Log\LoggerInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Throwable;

readonly class CloseExpiredPollsCron implements CronTaskInterface
{
    public function __construct(
        private FilmPollRepository $pollRepo,
        private PollService $pollService,
        private LoggerInterface $logger,
    ) {}

    public function getIdentifier(): string
    {
        return 'filmclub.close-expired-polls';
    }

    public function runCronTask(OutputInterface $output): CronTaskResult
    {
        $polls = $this->pollRepo->findExpiredActive();
        $closed = 0;
        $errors = 0;

        foreach ($polls as $poll) {
            try {
                $closure = $this->pollService->close($poll);

                if ($closure->winningSuggestion !== null) {
                    $this->pollService->commitOutcome($poll, $closure->winningSuggestion);
                }

                $closed++;
                $output->writeln(sprintf('CloseExpiredPollsCron: closed poll %d', $poll->getId()));
            } catch (Throwable $e) {
                $errors++;
                $this->logger->error('CloseExpiredPollsCron: failed to close poll', [
                    'poll_id' => $poll->getId(),
                    'error' => $e->getMessage(),
                ]);
                $output->writeln(sprintf('CloseExpiredPollsCron: error on poll %d: %s', $poll->getId(), $e->getMessage()));
            }
        }

        $message = sprintf('%d closed, %d errors', $closed, $errors);
        $status = $errors > 0 ? CronTaskStatus::error : CronTaskStatus::ok;

        return new CronTaskResult($this->getIdentifier(), $status, $message);
    }
}
