<?php declare(strict_types=1);

namespace Tests\Unit\Service\System;

use App\Enum\CronTaskStatus;
use App\Repository\CronLogRepository;
use App\Service\System\CronLogCleanupService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\BufferedOutput;

class CronLogCleanupServiceTest extends TestCase
{
    public function testIdentifierIsStable(): void
    {
        $service = new CronLogCleanupService($this->createStub(CronLogRepository::class));

        static::assertSame('cron-log-cleanup', $service->getIdentifier());
    }

    public function testRunCronTaskReturnsOkAndReportsDeletedCount(): void
    {
        // Arrange
        $repo = $this->createStub(CronLogRepository::class);
        $repo->method('deleteOlderThan')->willReturn(7);

        $service = new CronLogCleanupService($repo);
        $output = new BufferedOutput();

        // Act
        $result = $service->runCronTask($output);

        // Assert
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('7 deleted', $result->message);
        static::assertStringContainsString('7 deleted', $output->fetch());
    }

    public function testRunCronTaskCatchesRepositoryExceptions(): void
    {
        // Arrange
        $repo = $this->createStub(CronLogRepository::class);
        $repo->method('deleteOlderThan')->willThrowException(new RuntimeException('db down'));

        $service = new CronLogCleanupService($repo);
        $output = new BufferedOutput();

        // Act
        $result = $service->runCronTask($output);

        // Assert
        static::assertSame(CronTaskStatus::exception, $result->status);
        static::assertSame('db down', $result->message);
        static::assertStringContainsString('db down', $output->fetch());
    }
}
