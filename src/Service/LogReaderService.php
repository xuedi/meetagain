<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\ValueObjects\LogEntry;
use SplFileObject;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class LogReaderService
{
    private const int MAX_LIMIT = 500;
    private const int READ_MULTIPLIER = 10;

    public function __construct(
        #[Autowire('%kernel.logs_dir%')] private string $logsDir,
        #[Autowire('%kernel.environment%')] private string $environment,
    ) {}

    /** @return LogEntry[] */
    public function getRecentEntries(int $limit = 100, ?string $level = null, ?string $channel = null): array
    {
        $limit = min(max($limit, 1), self::MAX_LIMIT);
        $logFile = $this->getLogFilePath();

        if (!file_exists($logFile)) {
            return [];
        }

        $rawLimit = ($level !== null || $channel !== null)
            ? $limit * self::READ_MULTIPLIER
            : $limit;

        $lines = $this->readTail($logFile, $rawLimit);
        $entries = [];

        foreach ($lines as $line) {
            if ($line === '') {
                continue;
            }

            try {
                $entry = LogEntry::fromString($line);
            } catch (\Throwable) {
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

    public function getLogFilePath(): string
    {
        return $this->logsDir . '/' . $this->environment . '.log';
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
            $line = rtrim((string) $file->current(), "\n\r");
            if ($line !== '') {
                $lines[] = $line;
            }
            $file->next();
        }

        return array_reverse($lines);
    }
}
