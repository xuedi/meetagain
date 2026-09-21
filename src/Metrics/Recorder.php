<?php declare(strict_types=1);

namespace App\Metrics;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Service\ResetInterface;

final class Recorder implements ResetInterface
{
    private const int DEFAULT_PORT = 8089;
    private const int MAX_DATAGRAM_BYTES = 8192;
    private const int RESOLVE_CACHE_SECONDS = 60;

    private ?string $host = null;
    private int $port = self::DEFAULT_PORT;

    /** @var array<string, string> */
    private array $baseTags = [];

    /** @var list<Point> */
    private array $points = [];

    private ?string $resolvedIp = null;

    public function __construct(#[Autowire(env: 'default::METRICS_DSN')] ?string $dsn, string $environment)
    {
        $parts = parse_url((string) $dsn);
        $isUdp = is_array($parts) && ($parts['scheme'] ?? null) === 'udp';
        $host = $isUdp ? $parts['host'] ?? '' : '';
        if ($host === '') {
            return;
        }

        $query = [];
        parse_str($parts['query'] ?? '', $query);
        $app = is_string($query['app'] ?? null) && $query['app'] !== '' ? $query['app'] : 'app';

        $this->host = $host;
        $this->port = $parts['port'] ?? self::DEFAULT_PORT;
        $this->baseTags = ['app' => $app, 'env' => $environment];
    }

    public function isEnabled(): bool
    {
        return $this->host !== null;
    }

    public function add(Point $point): void
    {
        if ($this->host === null) {
            return;
        }
        $this->points[] = $point;
    }

    public function flush(): void
    {
        if ($this->points === []) {
            return;
        }

        $lines = array_filter(array_map($this->encode(...), $this->points));
        $this->points = [];

        $payload = '';
        foreach ($lines as $line) {
            $wouldOverflow = $payload !== '' && (strlen($payload) + strlen($line) + 1) > self::MAX_DATAGRAM_BYTES;
            if ($wouldOverflow) {
                $this->send($payload);
                $payload = '';
            }
            $payload .= ($payload === '' ? '' : "\n") . $line;
        }
        if ($payload !== '') {
            $this->send($payload);
        }
    }

    public function reset(): void
    {
        $this->points = [];
        $this->resolvedIp = null;
    }

    private function encode(Point $point): string
    {
        $fields = [];
        foreach ($point->fields as $key => $value) {
            $formatted = $this->formatField($value);
            if ($formatted !== null) {
                $fields[] = $this->escape($key, ', =') . '=' . $formatted;
            }
        }
        if ($fields === []) {
            return '';
        }

        $line = $this->escape($point->measurement, ', ');
        foreach ([...$this->baseTags, ...$point->tags] as $key => $value) {
            if ($value !== '') {
                $line .= ',' . $this->escape($key, ', =') . '=' . $this->escape($value, ', =');
            }
        }

        return $line . ' ' . implode(',', $fields);
    }

    private function formatField(int|float $value): ?string
    {
        if (is_int($value)) {
            return $value . 'i';
        }
        if (!is_finite($value)) {
            return null;
        }
        $formatted = rtrim(rtrim(sprintf('%.4F', $value), '0'), '.');

        return $formatted === '-0' ? '0' : $formatted;
    }

    private function escape(string $value, string $characters): string
    {
        return addcslashes(str_replace(["\r", "\n"], '', $value), $characters);
    }

    private function send(string $payload): void
    {
        $this->resolvedIp ??= $this->resolve();
        if ($this->resolvedIp === null) {
            return;
        }

        $errorCode = 0;
        $errorMessage = '';
        set_error_handler(static fn(): bool => true);
        try {
            $socket = stream_socket_client(sprintf('udp://%s:%d', $this->resolvedIp, $this->port), $errorCode, $errorMessage, 0);
            if ($socket !== false) {
                fwrite($socket, $payload);
                fclose($socket);
            }
        } finally {
            restore_error_handler();
        }
    }

    private function resolve(): ?string
    {
        $host = (string) $this->host;
        if (filter_var($host, FILTER_VALIDATE_IP) !== false) {
            return $host;
        }

        $cacheKey = 'metrics.resolve.' . $host;
        $canCache = function_exists('apcu_enabled') && apcu_enabled();
        $cached = $canCache ? apcu_fetch($cacheKey) : false;
        if (is_string($cached)) {
            return $cached === '' ? null : $cached;
        }

        $ip = gethostbyname($host);
        $resolved = $ip === $host ? '' : $ip;
        if ($canCache) {
            apcu_store($cacheKey, $resolved, self::RESOLVE_CACHE_SECONDS);
        }

        return $resolved === '' ? null : $resolved;
    }
}
