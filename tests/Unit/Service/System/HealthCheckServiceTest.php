<?php declare(strict_types=1);

namespace Tests\Unit\Service\System;

use App\ExtendedFilesystem;
use App\Service\System\HealthCheckService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;

class HealthCheckServiceTest extends TestCase
{
    private const string PROJECT_DIR = '/app';
    private const int MAX_SIZE = 50 * 1024 * 1024;

    public function testTestLogSizeReportsZeroSizeWhenLogFileMissing(): void
    {
        // Arrange
        $service = $this->makeService(logExists: false);

        // Act
        $result = $service->runAll()['logSize'];

        // Assert
        static::assertTrue($result['ok']);
        static::assertSame(0, $result['size']);
        static::assertSame(self::MAX_SIZE, $result['maxSize']);
    }

    /**
     * @param positive-int|0 $size
     */
    #[DataProvider('provideLogSizeCases')]
    public function testTestLogSizeReportsCorrectOkFlagForSize(int $size, bool $expectedOk): void
    {
        // Arrange
        $service = $this->makeService(logExists: true, logSize: $size);

        // Act
        $result = $service->runAll()['logSize'];

        // Assert
        static::assertSame($expectedOk, $result['ok']);
        static::assertSame($size, $result['size']);
    }

    public static function provideLogSizeCases(): iterable
    {
        yield 'just under threshold' => [self::MAX_SIZE - 1, true];
        yield 'at threshold is not ok' => [self::MAX_SIZE, false];
        yield 'just over threshold' => [self::MAX_SIZE + 1, false];
        yield 'small file' => [1024, true];
        yield 'empty file' => [0, true];
    }

    public function testTestLogSizeTreatsFalseSizeAsZero(): void
    {
        // Arrange - fileExists true but filesize() returns false (race / permissions)
        $service = $this->makeService(logExists: true, logSize: false);

        // Act
        $result = $service->runAll()['logSize'];

        // Assert
        static::assertTrue($result['ok']);
        static::assertSame(0, $result['size']);
    }

    private function makeService(bool $logExists, int|false $logSize = 0): HealthCheckService
    {
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn($logExists);
        $fs->method('getFileSize')->willReturn($logSize);
        // Stub disk-space lookups so runAll() never touches the real filesystem
        // (PROJECT_DIR is a sentinel that may not exist on the test host).
        $fs->method('getDiskFreeSpace')->willReturn(50.0 * 1024 * 1024 * 1024);
        $fs->method('getDiskTotalSpace')->willReturn(100.0 * 1024 * 1024 * 1024);

        return new HealthCheckService(new TagAwareAdapter(new ArrayAdapter()), $fs, self::PROJECT_DIR);
    }
}
