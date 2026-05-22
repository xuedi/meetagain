<?php declare(strict_types=1);

namespace Tests\Unit\Service\System;

use App\ExtendedFilesystem;
use App\Service\System\SystemLogService;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class SystemLogServiceTest extends TestCase
{
    private const string LOGS_DIR = '/var/log';
    private const string ENV = 'prod';

    public function testGetRecentEntriesReturnsEmptyWhenLogFileMissing(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('glob')->willReturn([]);
        $fs->method('fileExists')->willReturn(false);
        $service = new SystemLogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $entries = $service->getRecentEntries();

        // Assert
        static::assertSame([], $entries);
    }

    public function testGetRecentEntriesReturnsParsedLinesReversed(): void
    {
        // Arrange - three lines in chronological order in the file
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('glob')->willReturn([]);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn(implode("\n", [
            '[2026-05-12T10:00:00+00:00] app.INFO: first',
            '[2026-05-12T10:00:01+00:00] app.WARNING: second',
            '[2026-05-12T10:00:02+00:00] app.ERROR: third',
            '',
        ]));
        $service = new SystemLogService($fs, self::LOGS_DIR, self::ENV);

        // Act - newest first
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
        $fs->method('glob')->willReturn([]);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn(implode("\n", [
            '[2026-05-12T10:00:00+00:00] app.INFO: keep me out',
            '[2026-05-12T10:00:01+00:00] app.ERROR: keep me in',
        ]));
        $service = new SystemLogService($fs, self::LOGS_DIR, self::ENV);

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
        $fs->method('glob')->willReturn([]);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn(implode("\n", $lines));
        $service = new SystemLogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $entries = $service->getRecentEntries(limit: 2);

        // Assert - newest two
        static::assertCount(2, $entries);
        static::assertSame('msg4', $entries[0]->getMessage());
        static::assertSame('msg3', $entries[1]->getMessage());
    }

    public function testGetLogFilePathFallsBackToBaseWhenNoRotatedFiles(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('glob')->willReturn([]);
        $service = new SystemLogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $path = $service->getLogFilePath();

        // Assert
        static::assertSame(self::LOGS_DIR . '/' . self::ENV . '.log', $path);
    }

    public function testGetLogFilePathPicksNewestRotatedFile(): void
    {
        // Arrange - 'b' has higher mtime than 'a'
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('glob')->willReturn([
            self::LOGS_DIR . '/prod-2026-05-10.log',
            self::LOGS_DIR . '/prod-2026-05-12.log',
        ]);
        $fs->method('getFileModifiedTime')->willReturnCallback(static fn(string $p): int => match ($p) {
            '/var/log/prod-2026-05-10.log' => 100,
            '/var/log/prod-2026-05-12.log' => 200,
            default => 0,
        });
        $service = new SystemLogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $path = $service->getLogFilePath();

        // Assert
        static::assertSame('/var/log/prod-2026-05-12.log', $path);
    }

    /**
     * @param list<string> $globResult
     */
    #[DataProvider('provideDeleteCases')]
    public function testDeleteOlderThanRemovesOnlyDatedFilesBeforeCutoff(
        DateTimeImmutable $cutoff,
        array $globResult,
        bool $hasBaseFile,
        int $expectedDeletedCount,
    ): void {
        // Arrange
        $deleted = [];
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn($hasBaseFile);
        $fs->method('glob')->willReturn($globResult);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('deleteFile')->willReturnCallback(static function (string $path) use (&$deleted): bool {
            $deleted[] = $path;
            return true;
        });
        $service = new SystemLogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $count = $service->deleteOlderThan($cutoff);

        // Assert
        static::assertSame($expectedDeletedCount, $count);
        static::assertCount($expectedDeletedCount, $deleted);
        foreach ($deleted as $path) {
            static::assertStringNotContainsString(self::ENV . '.log', basename($path) === 'prod.log' ? 'prod.log' : '');
        }
    }

    public static function provideDeleteCases(): iterable
    {
        $cutoff = new DateTimeImmutable('2026-05-10');

        yield 'all rotated files predate cutoff' => [
            $cutoff,
            ['/var/log/prod-2026-05-01.log', '/var/log/prod-2026-05-08.log'],
            true,
            2,
        ];
        yield 'mixed: half kept half deleted' => [
            $cutoff,
            ['/var/log/prod-2026-05-08.log', '/var/log/prod-2026-05-11.log'],
            false,
            1,
        ];
        yield 'all rotated files are newer than cutoff' => [
            $cutoff,
            ['/var/log/prod-2026-05-11.log', '/var/log/prod-2026-05-12.log'],
            true,
            0,
        ];
        yield 'unmatched filename is ignored' => [
            $cutoff,
            ['/var/log/prod-archive.log'],
            true,
            0,
        ];
        yield 'base prod.log is never picked up by the date regex' => [
            $cutoff,
            [],
            true,
            0,
        ];
    }

    public function testGetAllEntriesConcatenatesAllLogFiles(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn(true);
        $fs->method('glob')->willReturn(['/var/log/prod-2026-05-11.log']);
        $fs->method('getFileContents')->willReturnCallback(static fn(string $p): string => match ($p) {
            '/var/log/prod.log' => '[2026-05-12T10:00:00+00:00] app.INFO: today',
            '/var/log/prod-2026-05-11.log' => '[2026-05-11T10:00:00+00:00] app.INFO: yesterday',
            default => '',
        });
        $service = new SystemLogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $entries = $service->getAllEntries();

        // Assert - both files contribute one entry each
        static::assertCount(2, $entries);
        $messages = array_map(static fn($e) => $e->getMessage(), $entries);
        static::assertContains('today', $messages);
        static::assertContains('yesterday', $messages);
    }

    public function testFindByHashReturnsMatchingEntry(): void
    {
        // Arrange
        $line = '[2026-05-12T10:00:00+00:00] app.ERROR: trackable {"k":"v"}';
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isFile')->willReturn(true);
        $fs->method('glob')->willReturn([]);
        $fs->method('getFileContents')->willReturn($line);
        $service = new SystemLogService($fs, self::LOGS_DIR, self::ENV);

        // Act - compute the expected hash via the same code path
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
        $fs->method('glob')->willReturn([]);
        $fs->method('getFileContents')->willReturn('[2026-05-12T10:00:00+00:00] app.INFO: msg');
        $service = new SystemLogService($fs, self::LOGS_DIR, self::ENV);

        // Act
        $found = $service->findByHash('0000000000000000');

        // Assert
        static::assertNull($found);
    }

    public function testCountLinesHandlesMissingFile(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('glob')->willReturn([]);
        $fs->method('fileExists')->willReturn(false);
        $service = new SystemLogService($fs, self::LOGS_DIR, self::ENV);

        // Act / Assert
        static::assertSame(0, $service->countLines());
    }

    public function testCountLinesCountsNewlines(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('glob')->willReturn([]);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn("one\ntwo\nthree\n");
        $service = new SystemLogService($fs, self::LOGS_DIR, self::ENV);

        // Act / Assert - trailing newline does not add an extra count
        static::assertSame(3, $service->countLines());
    }
}
