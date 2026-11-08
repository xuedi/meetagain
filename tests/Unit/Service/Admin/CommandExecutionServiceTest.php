<?php declare(strict_types=1);

namespace Tests\Unit\Service\Admin;

use App\Entity\CommandExecutionLog;
use App\Enum\CommandExecutionStatus;
use App\Enum\CommandTriggerType;
use App\Repository\CommandExecutionLogRepository;
use App\Service\Admin\CommandExecutionService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CommandExecutionServiceTest extends TestCase
{
    #[DataProvider('exitCodeProvider')]
    public function testCompleteDerivesTheStatusFromTheExitCode(int $exitCode, CommandExecutionStatus $expected): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $service = new CommandExecutionService($em, $this->createStub(CommandExecutionLogRepository::class));
        $log = $this->runningLog();

        // Act
        $service->complete($log, $exitCode, 'stdout', 'stderr');

        // Assert
        static::assertSame($expected, $log->getStatus());
        static::assertSame($exitCode, $log->getExitCode());
        static::assertSame('stdout', $log->getOutput());
        static::assertSame('stderr', $log->getErrorOutput());
        static::assertNotNull($log->getCompletedAt());
    }

    public static function exitCodeProvider(): iterable
    {
        yield 'zero is success' => [0, CommandExecutionStatus::Success];
        yield 'one is failed' => [1, CommandExecutionStatus::Failed];
        yield 'any other code is failed' => [255, CommandExecutionStatus::Failed];
    }

    public function testFailMarksTheLogFailedWithExitCodeOne(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $service = new CommandExecutionService($em, $this->createStub(CommandExecutionLogRepository::class));
        $log = $this->runningLog();

        // Act
        $service->fail($log, 'boom');

        // Assert
        static::assertSame(CommandExecutionStatus::Failed, $log->getStatus());
        static::assertSame(1, $log->getExitCode());
        static::assertSame('boom', $log->getErrorOutput());
        static::assertNotNull($log->getCompletedAt());
    }

    public function testTimeoutMarksTheLogTimedOutWithExitCode124(): void
    {
        // Arrange
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');
        $service = new CommandExecutionService($em, $this->createStub(CommandExecutionLogRepository::class));
        $log = $this->runningLog();

        // Act
        $service->timeout($log);

        // Assert
        static::assertSame(CommandExecutionStatus::Timeout, $log->getStatus());
        static::assertSame(124, $log->getExitCode());
        static::assertNotNull($log->getCompletedAt());
    }

    public function testGetStatsLooksBackTheGivenNumberOfHours(): void
    {
        // Arrange
        $since = null;
        $repo = $this->createStub(CommandExecutionLogRepository::class);
        $repo->method('getStats')->willReturnCallback(static function (DateTimeImmutable $value) use (&$since): array {
            $since = $value;

            return ['total' => 3, 'successful' => 2, 'failed' => 1];
        });
        $service = new CommandExecutionService($this->createStub(EntityManagerInterface::class), $repo);
        $expected = new DateTimeImmutable('-6 hours')->getTimestamp();

        // Act
        $stats = $service->getStats(6);

        // Assert
        static::assertSame(['total' => 3, 'successful' => 2, 'failed' => 1], $stats);
        static::assertEqualsWithDelta($expected, $since?->getTimestamp(), 5);
    }

    #[DataProvider('triggerProvider')]
    public function testStartSavesARunningLogForTheCommand(?CommandTriggerType $trigger, CommandTriggerType $expected): void
    {
        // Arrange
        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted = $entity;
        });
        $em->expects($this->once())->method('flush');
        $service = new CommandExecutionService($em, $this->createStub(CommandExecutionLogRepository::class));

        // Act
        $log = $trigger === null ? $service->start('app:cron') : $service->start('app:cron', $trigger);

        // Assert
        static::assertSame($log, $persisted);
        static::assertSame('app:cron', $log->getCommandName());
        static::assertSame(CommandExecutionStatus::Running, $log->getStatus());
        static::assertSame($expected, $log->getTriggeredBy());
        static::assertEqualsWithDelta(time(), $log->getStartedAt()?->getTimestamp(), 5);
        static::assertNull($log->getCompletedAt());
    }

    public static function triggerProvider(): iterable
    {
        yield 'cron when no trigger is given' => [null, CommandTriggerType::Cron];
        yield 'the given trigger otherwise' => [CommandTriggerType::Manual, CommandTriggerType::Manual];
    }

    public function testTheLookupsHandTheRepositorysAnswerThrough(): void
    {
        // Arrange
        $last = ['app:cron' => $this->runningLog()];
        $failed = [$this->runningLog()];
        $repo = $this->createMock(CommandExecutionLogRepository::class);
        $repo->method('getLastExecutionsByCommand')->willReturn($last);
        $repo->expects($this->once())->method('getRecentFailed')->with(3)->willReturn($failed);
        $service = new CommandExecutionService($this->createStub(EntityManagerInterface::class), $repo);

        // Act
        $lastExecutions = $service->getLastExecutions();
        $recentFailed = $service->getRecentFailed(3);

        // Assert
        static::assertSame($last, $lastExecutions);
        static::assertSame($failed, $recentFailed);
    }

    private function runningLog(): CommandExecutionLog
    {
        $log = new CommandExecutionLog();
        $log->setCommandName('app:cron');
        $log->setStartedAt(new DateTimeImmutable());
        $log->setStatus(CommandExecutionStatus::Running);
        $log->setTriggeredBy(CommandTriggerType::Cron);

        return $log;
    }
}
