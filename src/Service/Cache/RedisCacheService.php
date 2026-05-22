<?php declare(strict_types=1);

namespace App\Service\Cache;

use Redis;

final readonly class RedisCacheService
{
    private const int SCAN_BATCH = 1000;
    private const int LIST_HARD_CAP = 5000;
    private const int VALUE_PREVIEW_BYTES = 16384;

    public function __construct(
        private Redis $redis,
    ) {}

    /**
     * @param string|null $prefix limit to keys whose extracted prefix equals this value
     * @return array{keys: list<array{key: string, prefix: string, type: string, ttl: int, size: int}>, truncated: bool, totalScanned: int}
     */
    public function listKeys(?string $prefix = null, int $limit = self::LIST_HARD_CAP): array
    {
        $iterator = null;
        $this->redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);

        $keys = [];
        $scanned = 0;
        $truncated = false;

        do {
            $batch = $this->redis->scan($iterator, '*', self::SCAN_BATCH);
            if ($batch === false) {
                break;
            }
            foreach ($batch as $key) {
                $scanned++;
                $keyPrefix = $this->extractPrefix($key);
                if ($prefix !== null && $keyPrefix !== $prefix) {
                    continue;
                }
                $keys[] = [
                    'key' => $key,
                    'prefix' => $keyPrefix,
                    'type' => $this->typeLabel($this->intResult($this->redis->type($key))),
                    'ttl' => $this->intResult($this->redis->ttl($key)),
                    'size' => $this->approximateSize($key),
                ];
                if (count($keys) >= $limit) {
                    $truncated = true;
                    break 2;
                }
            }
        } while ($iterator > 0);

        usort($keys, static fn(array $a, array $b): int => strcmp($a['key'], $b['key']));

        return [
            'keys' => $keys,
            'truncated' => $truncated,
            'totalScanned' => $scanned,
        ];
    }

    /**
     * @return array<string, int> prefix => count, sorted by count descending
     */
    public function listPrefixes(): array
    {
        $iterator = null;
        $this->redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);

        $counts = [];
        do {
            $batch = $this->redis->scan($iterator, '*', self::SCAN_BATCH);
            if ($batch === false) {
                break;
            }
            foreach ($batch as $key) {
                $prefix = $this->extractPrefix($key);
                $counts[$prefix] = ($counts[$prefix] ?? 0) + 1;
            }
        } while ($iterator > 0);

        arsort($counts);

        return $counts;
    }

    /**
     * @return array{key: string, type: string, ttl: int, size: int, value: string, decoded: ?string, members: ?list<string>}|null
     */
    public function inspect(string $key): ?array
    {
        $type = $this->intResult($this->redis->type($key));
        if ($type === Redis::REDIS_NOT_FOUND) {
            return null;
        }

        $ttl = $this->intResult($this->redis->ttl($key));
        $size = $this->approximateSize($key);
        $value = '';
        $members = null;

        switch ($type) {
            case Redis::REDIS_STRING:
                $raw = $this->redis->get($key);
                $value = is_string($raw) ? $raw : '';
                break;
            case Redis::REDIS_LIST:
                $rawList = $this->arrayResult($this->redis->lRange($key, 0, 199));
                $members = array_map(static fn($v): string => (string) $v, $rawList);
                $value = implode("\n", array_map($this->stringify(...), $members));
                break;
            case Redis::REDIS_SET:
                $rawSet = $this->arrayResult($this->redis->sMembers($key));
                $members = array_map(static fn($v): string => (string) $v, $rawSet);
                $value = implode("\n", array_map($this->stringify(...), $members));
                break;
            case Redis::REDIS_ZSET:
                $rawZset = $this->arrayResult($this->redis->zRange($key, 0, 199, true));
                $members = [];
                foreach ($rawZset as $member => $score) {
                    $members[] = sprintf('%s  (%s)', $this->stringify((string) $member), (string) $score);
                }
                $value = implode("\n", $members);
                break;
            case Redis::REDIS_HASH:
                $rawHash = $this->arrayResult($this->redis->hGetAll($key));
                $members = [];
                foreach ($rawHash as $field => $fieldValue) {
                    $members[] = sprintf('%s = %s', $this->stringify((string) $field), $this->stringify((string) $fieldValue));
                }
                $value = implode("\n", $members);
                break;
        }

        $preview = $this->preview($value);
        $decoded = $this->tryDecode($preview);

        return [
            'key' => $key,
            'type' => $this->typeLabel($type),
            'ttl' => $ttl,
            'size' => $size,
            'value' => $preview,
            'decoded' => $decoded,
            'members' => $members,
        ];
    }

    private function extractPrefix(string $key): string
    {
        $remainder = $key;
        $colonPos = strpos($remainder, ':');
        if ($colonPos !== false && $colonPos > 0 && $colonPos < (strlen($remainder) - 1)) {
            $remainder = substr($remainder, $colonPos + 1);
        }

        $bracePos = strpos($remainder, '{');
        if ($bracePos !== false && $bracePos > 0) {
            $remainder = substr($remainder, 0, $bracePos);
        }

        $hit = strcspn($remainder, ':_.');
        if ($hit === 0 || $hit === strlen($remainder)) {
            return $remainder;
        }

        return substr($remainder, 0, $hit);
    }

    private function typeLabel(int $type): string
    {
        return match ($type) {
            Redis::REDIS_STRING => 'string',
            Redis::REDIS_SET => 'set',
            Redis::REDIS_LIST => 'list',
            Redis::REDIS_ZSET => 'zset',
            Redis::REDIS_HASH => 'hash',
            Redis::REDIS_STREAM => 'stream',
            default => 'unknown',
        };
    }

    private function approximateSize(string $key): int
    {
        $size = $this->redis->rawCommand('MEMORY', 'USAGE', $key);

        return is_int($size) ? $size : 0;
    }

    /**
     * phpredis returns the Redis instance when chained inside a MULTI/PIPELINE block; we never
     * call from that context, but the static analyser sees the union return type and balks.
     */
    private function intResult(mixed $value): int
    {
        return is_int($value) ? $value : 0;
    }

    /**
     * @return array<array-key, mixed>
     */
    private function arrayResult(mixed $value): array
    {
        return is_array($value) ? $value : [];
    }

    private function preview(string $value): string
    {
        if (strlen($value) <= self::VALUE_PREVIEW_BYTES) {
            return $value;
        }

        return substr($value, 0, self::VALUE_PREVIEW_BYTES);
    }

    private function tryDecode(string $value): ?string
    {
        if ($value === '') {
            return null;
        }

        $json = json_decode($value, true);
        if ($json !== null && json_last_error() === JSON_ERROR_NONE) {
            return json_encode($json, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE) ?: null;
        }

        set_error_handler(static fn(): bool => true);
        try {
            $unserialized = unserialize($value, ['allowed_classes' => false]);
        } finally {
            restore_error_handler();
        }
        if ($unserialized !== false || $value === 'b:0;') {
            return var_export($unserialized, true);
        }

        return null;
    }

    private function stringify(string $value): string
    {
        if (preg_match('//u', $value) === 1) {
            return $value;
        }

        return bin2hex($value);
    }
}
