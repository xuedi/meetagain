<?php declare(strict_types=1);

namespace Tests\Unit\Service\System;

use App\ExtendedFilesystem;
use App\Service\System\LogService;
use PHPUnit\Framework\TestCase;

class LogServiceTest extends TestCase
{
    private const string LOGS_DIR = '/var/log';
    private const string ENV = 'prod';

    public function testGetRecentEntriesReturnsEmptyWhenLogFileMissing(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn(false);
        $service = new LogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $entries = $service->getRecentEntries();

        // Assert
        static::assertSame([], $entries);
    }

    public function testGetRecentEntriesReturnsParsedLinesReversed(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn(true);
        $fs->method('getFileContents')->willReturn(implode("\n", [
            '[2026-05-12T10:00:00+00:00] app.INFO: first',
            '[2026-05-12T10:00:01+00:00] app.WARNING: second',
            '[2026-05-12T10:00:02+00:00] app.ERROR: third',
            '',
        ]));
        $service = new LogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $entries = $service->getRecentEntries();

        // Assert
        static::assertCount(3, $entries);
        static::assertSame('third', $entries[0]->getMessage());
        static::assertSame('second', $entries[1]->getMessage());
        static::assertSame('first', $entries[2]->getMessage());
    }

    public function testGetRecentEntriesHonoursLevelFilter(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn(true);
        $fs->method('getFileContents')->willReturn(implode("\n", [
            '[2026-05-12T10:00:00+00:00] app.INFO: keep me out',
            '[2026-05-12T10:00:01+00:00] app.ERROR: keep me in',
        ]));
        $service = new LogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $entries = $service->getRecentEntries(level: 'ERROR');

        // Assert
        static::assertCount(1, $entries);
        static::assertSame('keep me in', $entries[0]->getMessage());
    }

    public function testGetRecentEntriesClampsLimit(): void
    {
        // Arrange
        $lines = [];
        for ($i = 0; $i < 5; $i++) {
            $lines[] = sprintf('[2026-05-12T10:00:0%d+00:00] app.INFO: msg%d', $i, $i);
        }
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn(true);
        $fs->method('getFileContents')->willReturn(implode("\n", $lines));
        $service = new LogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $entries = $service->getRecentEntries(limit: 2);

        // Assert
        static::assertCount(2, $entries);
        static::assertSame('msg4', $entries[0]->getMessage());
        static::assertSame('msg3', $entries[1]->getMessage());
    }

    public function testClearDeletesTheLogFile(): void
    {
        // Arrange
        $deleted = [];
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn(true);
        $fs->method('deleteFile')->willReturnCallback(static function (string $path) use (&$deleted): bool {
            $deleted[] = $path;
            return true;
        });
        $service = new LogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $count = $service->clear();

        // Assert
        static::assertSame(1, $count);
        static::assertSame(['/var/log/prod.log'], $deleted);
    }

    public function testClearOnlyCountsFilesItActuallyRemoved(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn(true);
        $fs->method('deleteFile')->willReturn(false);
        $service = new LogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $count = $service->clear();

        // Assert
        static::assertSame(0, $count);
    }

    public function testFindByHashReturnsMatchingEntry(): void
    {
        // Arrange
        $line = '[2026-05-12T10:00:00+00:00] app.ERROR: trackable {"k":"v"}';
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn(true);
        $fs->method('getFileContents')->willReturn($line);
        $service = new LogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $expectedHash = $service->getAllEntries()[0]->getHash();
        $found = $service->findByHash($expectedHash);

        // Assert
        static::assertNotNull($found);
        static::assertSame('trackable', $found->getMessage());
    }

    public function testFindByHashReturnsNullForUnknownHash(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn(true);
        $fs->method('getFileContents')->willReturn('[2026-05-12T10:00:00+00:00] app.INFO: msg');
        $service = new LogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $found = $service->findByHash('0000000000000000');

        // Assert
        static::assertNull($found);
    }

    public function testCountAllLinesIsZeroWithoutAnyLogFile(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn(false);
        $service = new LogService($fs, self::LOGS_DIR, self::ENV);

        // Act / Assert
        static::assertSame(0, $service->countAllLines());
    }

    public function testCountAllLinesCountsAnUnterminatedLastLine(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn(true);
        $fs->method('getFileContents')->willReturn("one\ntwo\nthree");
        $service = new LogService($fs, self::LOGS_DIR, self::ENV);

        // Act / Assert
        static::assertSame(3, $service->countAllLines());
    }

    public function testGetTotalSizeIsTheLogFileSize(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn(true);
        $fs->method('getFileSize')->willReturn(1200);
        $service = new LogService($fs, self::LOGS_DIR, self::ENV);

        // Act / Assert
        static::assertSame(1200, $service->getTotalSize());
    }

    public function testGetTotalSizeIgnoresAnUnreadableFile(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn(true);
        $fs->method('getFileSize')->willReturn(false);
        $service = new LogService($fs, self::LOGS_DIR, self::ENV);

        // Act / Assert
        static::assertSame(0, $service->getTotalSize());
    }
}
