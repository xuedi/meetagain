<?php declare(strict_types=1);

namespace Tests\Unit\Stubs;

use App\Service\Cache\AtomicStoreInterface;
use Override;

final class InMemoryAtomicStore implements AtomicStoreInterface
{
    /** @var array<string, array<string, mixed>|true> */
    public array $values = [];

    /** @var array<string, array<string, int>> */
    public array $indexes = [];

    #[Override]
    public function claim(string $key, int $ttlSeconds): bool
    {
        if (array_key_exists($key, $this->values)) {
            return false;
        }

        $this->values[$key] = true;

        return true;
    }

    #[Override]
    public function consume(string $key): bool
    {
        if (!array_key_exists($key, $this->values)) {
            return false;
        }

        unset($this->values[$key]);

        return true;
    }

    #[Override]
    public function read(string $key): ?array
    {
        $value = $this->values[$key] ?? null;

        return is_array($value) ? $value : null;
    }

    #[Override]
    public function update(string $key, int $ttlSeconds, callable $mutate): void
    {
        $next = $mutate($this->read($key) ?? []);
        if ($next === null) {
            return;
        }

        $this->values[$key] = $next;
    }

    #[Override]
    public function delete(string ...$keys): void
    {
        foreach ($keys as $key) {
            unset($this->values[$key], $this->indexes[$key]);
        }
    }

    #[Override]
    public function indexAdd(string $index, string $member, int $expiresAt, int $indexTtlSeconds): void
    {
        $this->indexes[$index][$member] = $expiresAt;
    }

    #[Override]
    public function indexRemove(string $index, string $member): void
    {
        unset($this->indexes[$index][$member]);
    }

    #[Override]
    public function indexEntries(string $index): array
    {
        $now = time();

        return array_filter($this->indexes[$index] ?? [], static fn(int $expiresAt): bool => $expiresAt >= $now);
    }
}
