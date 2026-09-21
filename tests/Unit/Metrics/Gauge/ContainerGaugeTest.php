<?php declare(strict_types=1);

namespace Tests\Unit\Metrics\Gauge;

use App\Metrics\Gauge\ContainerGauge;
use App\Metrics\Point;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ContainerGaugeTest extends TestCase
{
    #[DataProvider('limitProvider')]
    public function testMemoryIsReadFromTheCgroup(string $limit, ?float $expectedLimitMb): void
    {
        // Arrange
        $dir = sys_get_temp_dir() . '/cgroup-' . bin2hex(random_bytes(4));
        mkdir($dir);
        file_put_contents($dir . '/memory.current', "268435456\n");
        file_put_contents($dir . '/memory.max', $limit . "\n");
        $gauge = new ContainerGauge($dir);

        // Act
        $points = iterator_to_array($gauge->collect(), false);

        // Assert
        static::assertCount(1, $points);
        static::assertInstanceOf(Point::class, $points[0]);
        static::assertSame(256.0, $points[0]->fields['memory_used_mb']);
        static::assertSame($expectedLimitMb, $points[0]->fields['memory_limit_mb'] ?? null);

        array_map(unlink(...), glob($dir . '/*'));
        rmdir($dir);
    }

    public static function limitProvider(): iterable
    {
        yield 'a numeric limit is reported' => ['536870912', 512.0];
        yield 'an unlimited cgroup reports no limit' => ['max', null];
    }
}
