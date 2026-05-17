<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Cron;

use App\Enum\CronTaskStatus;
use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Cron\CloseExpiredPollsCron;
use Plugin\Filmclub\Entity\Film;
use Plugin\Filmclub\Entity\FilmPoll;
use Plugin\Filmclub\Repository\FilmPollRepository;
use Plugin\Filmclub\Service\PollService;
use Plugin\Filmclub\ValueObject\PollClosure;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\Console\Output\OutputInterface;

class CloseExpiredPollsCronTest extends TestCase
{
    public function testIdentifierIsCorrect(): void
    {
        // Arrange
        $cron = $this->makeCron();

        // Act + Assert
        static::assertSame('filmclub.close-expired-polls', $cron->getIdentifier());
    }

    public function testNoExpiredPollsReturnsOkWithZeroCounts(): void
    {
        // Arrange
        $repo = $this->createStub(FilmPollRepository::class);
        $repo->method('findExpiredActive')->willReturn([]);

        $cron = $this->makeCron(pollRepo: $repo);
        $output = $this->createStub(OutputInterface::class);

        // Act
        $result = $cron->runCronTask($output);

        // Assert
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('0 closed, 0 errors', $result->message);
        static::assertSame('filmclub.close-expired-polls', $result->identifier);
    }

    public function testCleanWinnerCallsCloseAndCommitOutcome(): void
    {
        // Arrange
        $poll = $this->createStub(FilmPoll::class);
        $poll->method('getId')->willReturn(1);

        $winner = $this->createStub(Film::class);
        $closure = new PollClosure($winner, []);

        $repo = $this->createStub(FilmPollRepository::class);
        $repo->method('findExpiredActive')->willReturn([$poll]);

        $pollService = $this->createMock(PollService::class);
        $pollService->expects(static::once())->method('close')->with($poll)->willReturn($closure);
        $pollService->expects(static::once())->method('commitOutcome')->with($poll, $winner);

        $cron = $this->makeCron(pollRepo: $repo, pollService: $pollService);
        $output = $this->createStub(OutputInterface::class);

        // Act
        $result = $cron->runCronTask($output);

        // Assert
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('1 closed, 0 errors', $result->message);
    }

    public function testTiedPollCallsCloseButNotCommitOutcome(): void
    {
        // Arrange
        $poll = $this->createStub(FilmPoll::class);
        $poll->method('getId')->willReturn(2);

        $tiedA = $this->createStub(Film::class);
        $tiedB = $this->createStub(Film::class);
        $closure = new PollClosure(null, [$tiedA, $tiedB]);

        $repo = $this->createStub(FilmPollRepository::class);
        $repo->method('findExpiredActive')->willReturn([$poll]);

        $pollService = $this->createMock(PollService::class);
        $pollService->expects(static::once())->method('close')->with($poll)->willReturn($closure);
        $pollService->expects(static::never())->method('commitOutcome');

        $cron = $this->makeCron(pollRepo: $repo, pollService: $pollService);
        $output = $this->createStub(OutputInterface::class);

        // Act
        $result = $cron->runCronTask($output);

        // Assert
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('1 closed, 0 errors', $result->message);
    }

    public function testExceptionIsLoggedAndReturnsErrorStatus(): void
    {
        // Arrange
        $poll = $this->createStub(FilmPoll::class);
        $poll->method('getId')->willReturn(3);

        $repo = $this->createStub(FilmPollRepository::class);
        $repo->method('findExpiredActive')->willReturn([$poll]);

        $pollService = $this->createStub(PollService::class);
        $pollService->method('close')->willThrowException(new RuntimeException('something went wrong'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(static::once())->method('error');

        $cron = $this->makeCron(pollRepo: $repo, pollService: $pollService, logger: $logger);
        $output = $this->createStub(OutputInterface::class);

        // Act
        $result = $cron->runCronTask($output);

        // Assert
        static::assertSame(CronTaskStatus::error, $result->status);
        static::assertSame('0 closed, 1 errors', $result->message);
    }

    public function testMixedSuccessAndFailureReturnsErrorStatus(): void
    {
        // Arrange
        $pollOk = $this->createStub(FilmPoll::class);
        $pollOk->method('getId')->willReturn(10);

        $pollFail = $this->createStub(FilmPoll::class);
        $pollFail->method('getId')->willReturn(11);

        $winner = $this->createStub(Film::class);
        $closure = new PollClosure($winner, []);

        $repo = $this->createStub(FilmPollRepository::class);
        $repo->method('findExpiredActive')->willReturn([$pollOk, $pollFail]);

        $pollService = $this->createStub(PollService::class);
        $pollService->method('close')->willReturnCallback(
            static function (FilmPoll $poll) use ($pollFail, $closure): PollClosure {
                if ($poll === $pollFail) {
                    throw new RuntimeException('db error');
                }

                return $closure;
            },
        );

        $logger = $this->createStub(LoggerInterface::class);

        $cron = $this->makeCron(pollRepo: $repo, pollService: $pollService, logger: $logger);
        $output = $this->createStub(OutputInterface::class);

        // Act
        $result = $cron->runCronTask($output);

        // Assert
        static::assertSame(CronTaskStatus::error, $result->status);
        static::assertSame('1 closed, 1 errors', $result->message);
    }

    private function makeCron(
        ?FilmPollRepository $pollRepo = null,
        ?PollService $pollService = null,
        ?LoggerInterface $logger = null,
    ): CloseExpiredPollsCron {
        return new CloseExpiredPollsCron(
            pollRepo: $pollRepo ?? $this->createStub(FilmPollRepository::class),
            pollService: $pollService ?? $this->createStub(PollService::class),
            logger: $logger ?? $this->createStub(LoggerInterface::class),
        );
    }
}
