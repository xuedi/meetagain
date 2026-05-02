<?php declare(strict_types=1);

namespace App\Service\System;

use App\ValueObject\LogEntry;
use DateTimeImmutable;
use SplFileObject;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

readonly class SystemLogService
{
    public const int MAX_LIMIT = 1000;
    private const int READ_MULTIPLIER = 10;

    public function __construct(
        #[Autowire('%kernel.logs_dir%')]
        private string $logsDir,
        #[Autowire('%kernel.environment%')]
        private string $environment,
    ) {}

    /** @return LogEntry[] */
    public function getRecentEntries(int $limit = 100, ?string $level = null, ?string $channel = null): array
    {
        $limit = min(max($limit, 1), self::MAX_LIMIT);
        $logFile = $this->getLogFilePath();

        if (!file_exists($logFile)) {
            return [];
        }

        $rawLimit = $level !== null || $channel !== null ? $limit * self::READ_MULTIPLIER : $limit;

        $lines = $this->readTail($logFile, $rawLimit);
        $entries = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            try {
                $entry = LogEntry::fromString($line);
            } catch (Throwable) {
                continue;
            }

            if ($level !== null && strtoupper($entry->getLevel()) !== strtoupper($level)) {
                continue;
            }
            if ($channel !== null && strtolower($entry->getType()) !== strtolower($channel)) {
                continue;
            }

            $entries[] = $entry;

            if (count($entries) >= $limit) {
                break;
            }
        }

        return $entries;
    }

    public function countLines(): int
    {
        $logFile = $this->getLogFilePath();

        if (!file_exists($logFile)) {
            return 0;
        }

        $file = new SplFileObject($logFile, 'r');
        $file->seek(PHP_INT_MAX);

        return $file->key();
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
        $pattern = $this->logsDir . '/' . $this->environment . '-*.log';
        $files = glob($pattern);

        if ($files !== []) {
            usort($files, static fn ($a, $b) => filemtime($b) <=> filemtime($a));
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
            $content = @file_get_contents($file);
            if ($content === false) {
                continue;
            }
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

    /**
     * @param list<LogEntry> $entries
     * @param list<string> $levels
     */
    public function countByLevels(array $entries, array $levels): int
    {
        return count(array_filter(
            $entries,
            static fn (LogEntry $entry): bool => in_array(strtoupper($entry->getLevel()), $levels, true),
        ));
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
            if (!preg_match($pattern, basename($file), $m)) {
                continue;
            }
            try {
                $fileDate = new DateTimeImmutable($m[1]);
            } catch (Throwable) {
                continue;
            }
            if ($fileDate < $cutoff && @unlink($file)) {
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
        if (is_file($single)) {
            $files[] = $single;
        }
        $rotated = glob($this->logsDir . '/' . $this->environment . '-*.log') ?: [];
        $files = [...$files, ...$rotated];
        sort($files);

        return $files;
    }

    /** @return string[] Lines in reverse chronological order (newest first) */
    private function readTail(string $filePath, int $lineCount): array
    {
        $file = new SplFileObject($filePath, 'r');
        $file->seek(PHP_INT_MAX);
        $totalLines = $file->key();

        if ($totalLines === 0) {
            return [];
        }

        $startLine = max(0, $totalLines - $lineCount);
        $lines = [];

        $file->seek($startLine);
        while (!$file->eof()) {
            $current = $file->current();
            $line = rtrim(is_string($current) ? $current : '', "\n\r");
            if ($line !== '') {
                $lines[] = $line;
            }
            $file->next();
        }

        return array_reverse($lines);
    }
}
