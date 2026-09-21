<?php declare(strict_types=1);

namespace Tests\Unit\Metrics;

use App\Enum\CronTaskStatus;
use App\Metrics\GaugeCronTask;
use App\Metrics\GaugeInterface;
use App\Metrics\Point;
use App\Metrics\Recorder;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Output\NullOutput;

class GaugeCronTaskTest extends TestCase
{
    public function testDisabledMetricsSkipEveryGauge(): void
    {
        // Arrange
        $gauge = $this->createMock(GaugeInterface::class);
        $gauge->expects($this->never())->method('collect');
        $task = new GaugeCronTask(new Recorder(null, 'test'), [$gauge]);

        // Act
        $result = $task->runCronTask(new NullOutput());

        // Assert
        static::assertSame(CronTaskStatus::ok, $result->status);
        static::assertSame('disabled', $result->message);
    }

    public function testFailingGaugeIsSkippedAndTheOthersStillSend(): void
    {
        // Arrange
        $listener = new UdpListener();
        $recorder = new Recorder($listener->dsn(), 'prod');
        $failing = $this->createStub(GaugeInterface::class);
        $failing->method('collect')->willThrowException(new RuntimeException('down'));
        $working = $this->createStub(GaugeInterface::class);
        $working->method('collect')->willReturn([new Point('valkey', ['connected_clients' => 3])]);
        $task = new GaugeCronTask($recorder, [$failing, $working]);

        // Act
        $result = $task->runCronTask(new NullOutput());
        $recorder->flush();

        // Assert
        static::assertSame(CronTaskStatus::warning, $result->status);
        static::assertStringContainsString('down', $result->message);
        static::assertSame(['valkey,app=test,env=prod connected_clients=3i'], $listener->receive());
    }
}
