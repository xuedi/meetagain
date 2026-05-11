<?php declare(strict_types=1);

namespace Tests\Unit\Service\System;

use App\ExtendedFilesystem;
use App\Service\System\HealthCheckService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\TagAwareAdapter;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class HealthCheckServiceTest extends TestCase
{
    private const string PROJECT_DIR = '/app';
    private const int MAX_SIZE = 50 * 1024 * 1024;

    public function testTestLogSizeReportsZeroSizeWhenLogFileMissing(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(false);
        $service = new HealthCheckService(
            new TagAwareAdapter(new ArrayAdapter()),
            $fs,
            self::PROJECT_DIR,
        );

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
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileSize')->willReturn($size);
        $service = new HealthCheckService(
            new TagAwareAdapter(new ArrayAdapter()),
            $fs,
            self::PROJECT_DIR,
        );

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
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileSize')->willReturn(false);
        $service = new HealthCheckService(
            new TagAwareAdapter(new ArrayAdapter()),
            $fs,
            self::PROJECT_DIR,
        );

        // Act
        $result = $service->runAll()['logSize'];

        // Assert
        static::assertTrue($result['ok']);
        static::assertSame(0, $result['size']);
    }
}
