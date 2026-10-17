<?php declare(strict_types=1);

namespace App\Service\Cache;

/**
 * Keys shared by every worker; each call is atomic across all of them.
 */
interface AtomicStoreInterface
{
    public function claim(string $key, int $ttlSeconds): bool;

    public function consume(string $key): bool;

    /**
     * @return array<string, mixed>|null
     */
    public function read(string $key): ?array;

    /**
     * @param callable(array<string, mixed>): ?array<string, mixed> $mutate returning null leaves the value unchanged
     */
    public function update(string $key, int $ttlSeconds, callable $mutate): void;

    public function delete(string ...$keys): void;

    public function indexAdd(string $index, string $member, int $expiresAt, int $indexTtlSeconds): void;

    public function indexRemove(string $index, string $member): void;

    /**
     * @return array<string, int> member => expiresAt, expired members already dropped
     */
    public function indexEntries(string $index): array;
}
