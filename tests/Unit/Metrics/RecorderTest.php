<?php declare(strict_types=1);

namespace Tests\Unit\Metrics;

use App\Metrics\Point;
use App\Metrics\Recorder;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecorderTest extends TestCase
{
    #[DataProvider('dsnProvider')]
    public function testDsnDecidesWhetherMetricsAreEnabled(?string $dsn, bool $expected): void
    {
        // Arrange
        $recorder = new Recorder($dsn, 'test');

        // Act
        $enabled = $recorder->isEnabled();

        // Assert
        static::assertSame($expected, $enabled);
    }

    public static function dsnProvider(): iterable
    {
        yield 'unset variable is off' => [null, false];
        yield 'empty variable is off' => ['', false];
        yield 'non-udp scheme is off' => ['http://victoriametrics:8428', false];
        yield 'udp without host is off' => ['udp://', false];
        yield 'udp with host and port is on' => ['udp://victoriametrics:8089?app=meetagain', true];
        yield 'udp without port is on' => ['udp://victoriametrics', true];
    }

    #[DataProvider('encodingProvider')]
    public function testPointIsEncodedAsLineProtocol(Point $point, string $expected): void
    {
        // Arrange
        $listener = new UdpListener();
        $recorder = new Recorder($listener->dsn(), 'prod');
        $recorder->add($point);

        // Act
        $recorder->flush();

        // Assert
        static::assertSame([$expected], $listener->receive());
    }

    public static function encodingProvider(): iterable
    {
        yield 'integers get the i suffix, floats lose trailing zeros' => [
            new Point('http_request', ['db_queries' => 12, 'duration_ms' => 40.50, 'db_ms' => 0.0]),
            'http_request,app=test,env=prod db_queries=12i,duration_ms=40.5,db_ms=0',
        ];
        yield 'point tags follow the base tags' => [
            new Point('cron_task', ['duration_ms' => 3], ['task' => 'email-queue', 'status' => 'ok']),
            'cron_task,app=test,env=prod,task=email-queue,status=ok duration_ms=3i',
        ];
        yield 'commas, spaces and equals signs in tags are escaped' => [
            new Point('http_request', ['duration_ms' => 1], ['route' => 'a b,c=d']),
            'http_request,app=test,env=prod,route=a\ b\,c\=d duration_ms=1i',
        ];
        yield 'empty tag values are dropped' => [
            new Point('http_request', ['duration_ms' => 1], ['host' => '']),
            'http_request,app=test,env=prod duration_ms=1i',
        ];
        yield 'non-finite fields are dropped' => [
            new Point('database', ['ratio' => NAN, 'questions' => 7]),
            'database,app=test,env=prod questions=7i',
        ];
    }

    public function testMissingAppNameFallsBackToApp(): void
    {
        // Arrange
        $listener = new UdpListener();
        $recorder = new Recorder($listener->dsn('other=1'), 'dev');
        $recorder->add(new Point('deploy', ['value' => 1]));

        // Act
        $recorder->flush();

        // Assert
        static::assertSame(['deploy,app=app,env=dev value=1i'], $listener->receive());
    }

    public function testPointWithoutUsableFieldsSendsNothing(): void
    {
        // Arrange
        $listener = new UdpListener();
        $recorder = new Recorder($listener->dsn(), 'prod');
        $recorder->add(new Point('database', ['ratio' => INF]));

        // Act
        $recorder->flush();

        // Assert
        static::assertSame([], $listener->receive());
    }

    public function testLargeBatchIsSplitIntoSeveralDatagrams(): void
    {
        // Arrange
        $listener = new UdpListener();
        $recorder = new Recorder($listener->dsn(), 'prod');
        for ($i = 0; $i < 400; $i++) {
            $recorder->add(new Point('database_table', ['rows' => $i], ['table' => 'table_' . $i]));
        }

        // Act
        $recorder->flush();

        // Assert
        $datagrams = $listener->receive();
        static::assertGreaterThan(1, count($datagrams));
        static::assertLessThanOrEqual(8192, max(array_map(strlen(...), $datagrams)));
        static::assertSame(400, substr_count(implode("\n", $datagrams), 'database_table,'));
    }

    public function testFlushEmptiesTheBuffer(): void
    {
        // Arrange
        $listener = new UdpListener();
        $recorder = new Recorder($listener->dsn(), 'prod');
        $recorder->add(new Point('deploy', ['value' => 1]));
        $recorder->flush();
        $listener->receive();

        // Act
        $recorder->flush();

        // Assert
        static::assertSame([], $listener->receive());
    }

    public function testUnresolvableHostIsSilentlyIgnored(): void
    {
        // Arrange
        $recorder = new Recorder('udp://metrics-host-that-does-not-exist.invalid:8089', 'prod');
        $recorder->add(new Point('deploy', ['value' => 1]));

        // Act
        $recorder->flush();

        // Assert
        static::assertTrue($recorder->isEnabled());
    }
}
