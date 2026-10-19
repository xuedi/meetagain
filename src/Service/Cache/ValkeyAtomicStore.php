<?php declare(strict_types=1);

namespace App\Service\Cache;

use Override;
use Redis;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Throwable;

final readonly class ValkeyAtomicStore implements AtomicStoreInterface
{
    private const int UPDATE_ATTEMPTS = 5;

    public function __construct(
        private Redis $redis,
        #[Autowire('ma:%kernel.environment%:')]
        private string $prefix,
    ) {}

    #[Override]
    public function claim(string $key, int $ttlSeconds): bool
    {
        return $this->redis->set($this->prefix . $key, '1', ['nx', 'ex' => $ttlSeconds]) === true;
    }

    #[Override]
    public function consume(string $key): bool
    {
        return $this->redis->del($this->prefix . $key) === 1;
    }

    #[Override]
    public function read(string $key): ?array
    {
        return $this->decode($this->redis->get($this->prefix . $key));
    }

    #[Override]
    public function update(string $key, int $ttlSeconds, callable $mutate): void
    {
        $fullKey = $this->prefix . $key;
        for ($attempt = 1; $attempt <= self::UPDATE_ATTEMPTS; $attempt++) {
            $this->redis->watch($fullKey);
            try {
                $next = $mutate($this->decode($this->redis->get($fullKey)) ?? []);
            } catch (Throwable $e) {
                $this->redis->unwatch();

                throw $e;
            }
            if ($next === null) {
                $this->redis->unwatch();

                return;
            }

            $this->redis->multi();
            $this->redis->set($fullKey, $this->encode($next), ['ex' => $ttlSeconds]);
            if ($this->redis->exec() !== false) {
                return;
            }
        }

        throw new RuntimeException(sprintf('Gave up updating %s after %d conflicting writes', $key, self::UPDATE_ATTEMPTS));
    }

    #[Override]
    public function delete(string ...$keys): void
    {
        if ($keys === []) {
            return;
        }

        $this->redis->del(array_map(fn(string $key): string => $this->prefix . $key, $keys));
    }

    #[Override]
    public function indexAdd(string $index, string $member, int $expiresAt, int $indexTtlSeconds): void
    {
        $fullKey = $this->prefix . $index;
        $this->redis->zAdd($fullKey, $expiresAt, $member);
        $this->redis->expire($fullKey, $indexTtlSeconds);
    }

    #[Override]
    public function indexRemove(string $index, string $member): void
    {
        $this->redis->zRem($this->prefix . $index, $member);
    }

    #[Override]
    public function indexEntries(string $index): array
    {
        $fullKey = $this->prefix . $index;
        $this->redis->zRemRangeByScore($fullKey, '-inf', '(' . time());
        $raw = $this->redis->zRange($fullKey, 0, -1, ['withscores' => true]);
        if (!is_array($raw)) {
            return [];
        }

        $entries = [];
        foreach ($raw as $member => $score) {
            $entries[(string) $member] = (int) $score;
        }

        return $entries;
    }

    /**
     * @param array<string, mixed> $value
     */
    private function encode(array $value): string
    {
        return json_encode($value, JSON_THROW_ON_ERROR);
    }

    /**
     * @return array<string, mixed>|null
     */
    private function decode(mixed $raw): ?array
    {
        if (!is_string($raw)) {
            return null;
        }

        $value = json_decode($raw, true);

        return is_array($value) ? $value : null;
    }
}
