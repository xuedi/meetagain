<?php declare(strict_types=1);

namespace Module\Task\Tests\Unit\Internal;

use App\Metrics\Recorder;
use Module\Task\Contract\Status;
use Module\Task\Internal\Entity\Run;
use Module\Task\Internal\Registry;
use Module\Task\Internal\Repository\RunRepository;
use Module\Task\Internal\Runner;
use Module\Task\Tests\Stub\Task;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\MockClock;

final class RunnerTest extends TestCase
{
    public function testASuccessfulRunIsRecordedWithItsResult(): void
    {
        // Arrange
        $recorded = [];
        $runner = $this->runner(new Task(), $recorded, $this->createStub(LoggerInterface::class));

        // Act
        $result = $runner->run(Task::IDENTIFIER);

        // Assert
        self::assertSame(Status::ok, $result->status);
        self::assertCount(1, $recorded);
        self::assertSame(Task::IDENTIFIER, $recorded[0]->getIdentifier());
        self::assertSame(Status::ok, $recorded[0]->getStatus());
        self::assertSame('run 1', $recorded[0]->getMessage());
        self::assertSame('2026-10-17 12:00:00', $recorded[0]->getStartedAt()->format('Y-m-d H:i:s'));
    }

    public function testAThrowingTaskIsRecordedAsAnExceptionAndLogged(): void
    {
        // Arrange
        $task = new Task();
        $task->failWith = 'provider down';
        $recorded = [];
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::once())->method('error');
        $runner = $this->runner($task, $recorded, $logger);

        // Act
        $result = $runner->run(Task::IDENTIFIER);

        // Assert
        self::assertSame(Status::exception, $result->status);
        self::assertSame('provider down', $result->message);
        self::assertSame(Status::exception, $recorded[0]->getStatus());
    }

    public function testAnUnknownIdentifierRecordsNothing(): void
    {
        // Arrange
        $recorded = [];
        $runner = $this->runner(new Task(), $recorded, $this->createStub(LoggerInterface::class));

        // Act
        $result = $runner->run('missing');

        // Assert
        self::assertSame(Status::error, $result->status);
        self::assertSame([], $recorded);
    }

    /**
     * @param list<Run> $recorded
     */
    private function runner(Task $task, array &$recorded, LoggerInterface $logger): Runner
    {
        $repository = $this->createStub(RunRepository::class);
        $repository
            ->method('record')
            ->willReturnCallback(static function (Run $run) use (&$recorded): void {
                $recorded[] = $run;
            });

        return new Runner(new Registry([$task]), $repository, new MockClock('2026-10-17 12:00:00'), $logger, new Recorder(null, 'test'));
    }
}
