<?php declare(strict_types=1);

namespace Module\Task\Internal;

use App\Metrics\Point;
use App\Metrics\Recorder;
use Module\Task\Contract\Result;
use Module\Task\Contract\Status;
use Module\Task\Internal\Entity\Run;
use Module\Task\Internal\Message\RunTask;
use Module\Task\Internal\Repository\RunRepository;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Throwable;

#[AsMessageHandler]
readonly class Runner
{
    private const int BYTES_PER_MB = 1_048_576;

    public function __construct(
        private Registry $registry,
        private RunRepository $runRepository,
        private ClockInterface $clock,
        private LoggerInterface $logger,
        private Recorder $metrics,
    ) {}

    public function __invoke(RunTask $message): void
    {
        $this->run($message->identifier);
    }

    public function run(string $identifier): Result
    {
        $task = $this->registry->find($identifier);
        if ($task === null) {
            $this->logger->error('Scheduled task {identifier} is not registered', ['identifier' => $identifier]);

            return new Result(Status::error, sprintf('No task is registered as "%s".', $identifier));
        }

        $startedAt = $this->clock->now();
        memory_reset_peak_usage();
        $start = (int) hrtime(true);
        try {
            $result = $task->run();
        } catch (Throwable $exception) {
            $this->logger->error('Task {identifier} failed: {message}', [
                'identifier' => $identifier,
                'message' => $exception->getMessage(),
                'exception' => $exception,
            ]);
            $result = new Result(Status::exception, $exception->getMessage());
        }
        $durationMs = intdiv((int) hrtime(true) - $start, 1_000_000);

        $this->runRepository->record(new Run($identifier, $startedAt, $durationMs, $result->status, $result->message, (string) gethostname()));
        $this->metrics->add(
            new Point(
                'task_run',
                ['duration_ms' => $durationMs, 'memory_peak_mb' => round(memory_get_peak_usage(true) / self::BYTES_PER_MB, 1)],
                ['task' => $identifier, 'status' => $result->status->value],
            ),
        );

        return $result;
    }
}
