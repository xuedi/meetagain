<?php declare(strict_types=1);

namespace Tests\Unit\Service\System;

use App\Entity\CronLog;
use App\Enum\CronTaskStatus;
use App\Repository\CronLogRepository;
use App\Service\System\CronIntervalCheckService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Output\NullOutput;

class CronIntervalCheckServiceTest extends TestCase
{
    private const string NOW = '2026-04-21 22:43:30';

    public function testFirstEverRunReturnsOkWithoutComparison(): void
    {
        // Arrange
        $repo = $this->createStub(CronLogRepository::class);
        $repo->method('findMostRecent')->willReturn(null);

        $service = new CronIntervalCheckService($repo, new MockClock(new DateTimeImmutable(self::NOW)));

        // Act
        $result = $service->runCronTask(new NullOutput());

        // Assert
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('first run', $result->message);
    }

    #[DataProvider('gapProvider')]
    public function testGapClassification(string $previousRunAt, CronTaskStatus $expected, string $expectedMessagePart): void
    {
        // Arrange
        $previousLog = new CronLog(
            new DateTimeImmutable($previousRunAt),
            CronTaskStatus::ok,
            0,
            [],
        );

        $repo = $this->createStub(CronLogRepository::class);
        $repo->method('findMostRecent')->willReturn($previousLog);

        $service = new CronIntervalCheckService($repo, new MockClock(new DateTimeImmutable(self::NOW)));

        // Act
        $result = $service->runCronTask(new NullOutput());

        // Assert
        static::assertSame($expected, $result->status);
        static::assertStringContainsString($expectedMessagePart, $result->message);
    }

    public static function gapProvider(): iterable
    {
        // NOW = 2026-04-21 22:43:30. Thresholds: ok <=600s, warning 601-1200s, error >1200s.
        yield '30s ago - healthy tight interval' => ['2026-04-21 22:43:00', CronTaskStatus::ok, 'gap: 30s'];
        yield '5 min ago - normal cadence' => ['2026-04-21 22:38:30', CronTaskStatus::ok, 'gap: 300s'];
        yield '10 min ago - exactly on warning threshold, still ok' => ['2026-04-21 22:33:30', CronTaskStatus::ok, 'gap: 600s'];
        yield '10 min 1s ago - warning' => ['2026-04-21 22:33:29', CronTaskStatus::warning, 'warning threshold: 600s'];
        yield '15 min ago - mid-warning band' => ['2026-04-21 22:28:30', CronTaskStatus::warning, 'warning threshold'];
        yield '20 min ago - exactly on error threshold, still warning' => ['2026-04-21 22:23:30', CronTaskStatus::warning, 'warning threshold'];
        yield '20 min 1s ago - error' => ['2026-04-21 22:23:29', CronTaskStatus::error, 'error threshold: 1200s'];
        yield '4h gap (the production incident on 2026-04-21)' => ['2026-04-21 18:32:16', CronTaskStatus::error, '15074s'];
    }

    public function testWarningMessageIncludesThreshold(): void
    {
        // Arrange: 11 min gap (inside warning band)
        $previousLog = new CronLog(
            new DateTimeImmutable('2026-04-21 22:32:30'),
            CronTaskStatus::ok,
            0,
            [],
        );

        $repo = $this->createStub(CronLogRepository::class);
        $repo->method('findMostRecent')->willReturn($previousLog);

        $service = new CronIntervalCheckService($repo, new MockClock(new DateTimeImmutable(self::NOW)));

        // Act
        $result = $service->runCronTask(new NullOutput());

        // Assert
        static::assertSame(CronTaskStatus::warning, $result->status);
        static::assertStringContainsString('warning threshold: 600s', $result->message);
    }

    public function testErrorMessageIncludesThreshold(): void
    {
        // Arrange: 30 min gap (past error threshold)
        $previousLog = new CronLog(
            new DateTimeImmutable('2026-04-21 22:13:30'),
            CronTaskStatus::ok,
            0,
            [],
        );

        $repo = $this->createStub(CronLogRepository::class);
        $repo->method('findMostRecent')->willReturn($previousLog);

        $service = new CronIntervalCheckService($repo, new MockClock(new DateTimeImmutable(self::NOW)));

        // Act
        $result = $service->runCronTask(new NullOutput());

        // Assert
        static::assertSame(CronTaskStatus::error, $result->status);
        static::assertStringContainsString('error threshold: 1200s', $result->message);
    }

    public function testIdentifierIsStable(): void
    {
        $service = new CronIntervalCheckService(
            $this->createStub(CronLogRepository::class),
            new MockClock(),
        );

        static::assertSame('cron-interval-check', $service->getIdentifier());
    }
}
