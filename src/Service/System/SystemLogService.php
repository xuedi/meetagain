<?php declare(strict_types=1);

namespace App\Service\System;

use App\ExtendedFilesystem;
use App\ValueObject\LogEntry;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

readonly class SystemLogService
{
    public const int MAX_LIMIT = 1000;

    public function __construct(
        private ExtendedFilesystem $fs,
        #[Autowire('%kernel.logs_dir%')]
        private string $logsDir,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {}

    /** @return list<LogEntry> */
    public function getRecentEntries(int $limit = 100, ?string $level = null, ?string $channel = null): array
    {
        $limit = min(max($limit, 1), self::MAX_LIMIT);
        $logFile = $this->getLogFilePath();

        if (!$this->fs->fileExists($logFile)) {
            return [];
        }

        $entries = array_reverse($this->readEntriesFrom($logFile));
        $result = [];
        foreach ($entries as $entry) {
            if ($level !== null && strtoupper($entry->getLevel()) !== strtoupper($level)) {
                continue;
            }
            if ($channel !== null && strtolower($entry->getType()) !== strtolower($channel)) {
                continue;
            }
            $result[] = $entry;
            if (count($result) >= $limit) {
                break;
            }
        }

        return $result;
    }

    public function countLines(): int
    {
        $logFile = $this->getLogFilePath();
        if (!$this->fs->fileExists($logFile)) {
            return 0;
        }

        $content = $this->fs->getFileContents($logFile);
        if ($content === false || $content === '') {
            return 0;
        }

        return substr_count($content, "\n") + (str_ends_with($content, "\n") ? 0 : 1);
    }

    public function getLatestTimestamp(): ?DateTimeImmutable
    {
        $entries = $this->getRecentEntries(1);
        if ($entries === []) {
            return null;
        }

        return $entries[0]->getDate();
    }

    public function getLogFilePath(): string
    {
        $files = $this->fs->glob($this->logsDir . '/' . $this->environment . '-*.log');

        if ($files !== []) {
            usort($files, fn($a, $b) => ($this->fs->getFileModifiedTime($b) ?: 0) <=> ($this->fs->getFileModifiedTime($a) ?: 0));
            return $files[0];
        }

        return $this->logsDir . '/' . $this->environment . '.log';
    }

    /**
     * Reads every log file for the current environment in chronological order.
     * Picks up both the non-rotated `{env}.log` and the daily rotated
     * `{env}-YYYY-MM-DD.log` files.
     *
     * @return list<LogEntry>
     */
    public function getAllEntries(): array
    {
        $entries = [];
        foreach ($this->resolveAllLogFiles() as $file) {
            foreach ($this->readEntriesFrom($file) as $entry) {
                $entries[] = $entry;
            }
        }

        return $entries;
    }

    /**
     * @param list<LogEntry> $entries
     * @param list<string>|null $levels Monolog level names to keep; null/empty means no level filter.
     * @return list<LogEntry>
     */
    public function filterEntries(array $entries, ?DateTimeImmutable $since, ?array $levels): array
    {
        if ($since === null && ($levels === null || $levels === [])) {
            return $entries;
        }

        return array_values(array_filter($entries, static function (LogEntry $entry) use ($since, $levels): bool {
            if ($since !== null && $entry->getDate() < $since) {
                return false;
            }
            if ($levels !== null && $levels !== [] && !in_array(strtoupper($entry->getLevel()), $levels, true)) {
                return false;
            }

            return true;
        }));
    }

    public function findByHash(string $hash): ?LogEntry
    {
        foreach ($this->getAllEntries() as $entry) {
            if ($entry->getHash() === $hash) {
                return $entry;
            }
        }

        return null;
    }

    /**
     * @param list<LogEntry> $entries
     * @param list<string> $levels
     */
    public function countByLevels(array $entries, array $levels): int
    {
        return count(array_filter($entries, static fn(LogEntry $entry): bool => in_array(strtoupper($entry->getLevel()), $levels, true)));
    }

    /**
     * Deletes every dated rotated log file whose filename-encoded date is older
     * than `$cutoff`. The active non-rotated `{env}.log` and any file whose
     * name does not match the `{env}-YYYY-MM-DD.log` pattern are left alone.
     *
     * @return int number of deleted files
     */
    public function deleteOlderThan(DateTimeImmutable $cutoff): int
    {
        $deleted = 0;
        $pattern = '/^' . preg_quote($this->environment, '/') . '-(\d{4}-\d{2}-\d{2})\.log$/';
        foreach ($this->resolveAllLogFiles() as $file) {
            $m = [];
            if (!preg_match($pattern, basename($file), $m)) {
                continue;
            }
            try {
                $fileDate = new DateTimeImmutable($m[1]);
            } catch (Throwable) {
                continue;
            }
            if ($fileDate < $cutoff && $this->fs->fileExists($file) && $this->fs->deleteFile($file)) {
                $deleted++;
            }
        }

        return $deleted;
    }

    /**
     * @return list<string>
     */
    private function resolveAllLogFiles(): array
    {
        $files = [];
        $single = $this->logsDir . '/' . $this->environment . '.log';
        if ($this->fs->isFile($single)) {
            $files[] = $single;
        }
        $rotated = $this->fs->glob($this->logsDir . '/' . $this->environment . '-*.log');
        $files = [...$files, ...$rotated];
        sort($files);

        return $files;
    }

    /**
     * @return list<LogEntry>
     */
    private function readEntriesFrom(string $file): array
    {
        $content = $this->fs->getFileContents($file);
        if ($content === false) {
            return [];
        }

        $entries = [];
        foreach (explode("\n", $content) as $line) {
            if ($line === '' || $line === '0') {
                continue;
            }
            try {
                $entries[] = LogEntry::fromString($line);
            } catch (Throwable) {
                continue;
            }
        }

        return $entries;
    }
}
