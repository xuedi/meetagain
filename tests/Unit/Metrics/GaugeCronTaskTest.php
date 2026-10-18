<?php declare(strict_types=1);

namespace Tests\Unit\Metrics;

use App\Metrics\GaugeCronTask;
use App\Metrics\GaugeInterface;
use App\Metrics\Point;
use App\Metrics\Recorder;
use Module\Task\Contract\Status;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class GaugeCronTaskTest extends TestCase
{
    public function testDisabledMetricsSkipEveryGauge(): void
    {
        // Arrange
        $gauge = $this->createMock(GaugeInterface::class);
        $gauge->expects($this->never())->method('collect');
        $task = new GaugeCronTask(new Recorder(null, 'test'), [$gauge]);

        // Act
        $result = $task->run();

        // Assert
        static::assertSame(Status::ok, $result->status);
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
        $result = $task->run();
        $recorder->flush();

        // Assert
        static::assertSame(Status::warning, $result->status);
        static::assertStringContainsString('down', $result->message);
        static::assertSame(['valkey,app=test,env=prod connected_clients=3i'], $listener->receive());
    }
}
