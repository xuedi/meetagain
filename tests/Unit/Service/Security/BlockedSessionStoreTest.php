<?php declare(strict_types=1);

namespace Tests\Unit\Service\Security;

use App\Service\Security\BlockedSessionStore;
use Closure;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Cache\CacheItemInterface;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class BlockedSessionStoreTest extends TestCase
{
    /**
     * @param Closure(BlockedSessionStore, string, array<string, mixed>): void $block
     * @param Closure(BlockedSessionStore, string): bool $isBlocked
     * @param Closure(BlockedSessionStore, string): ?array<string, mixed> $getSnapshot
     * @param Closure(BlockedSessionStore, string): void $unblock
     * @param Closure(BlockedSessionStore): list<array{key: string, snapshot: array<string, mixed>}> $list
     */
    #[DataProvider('provideAxisCases')]
    public function testBlockAndUnblockLifecycleAcrossBothAxes(Closure $block, Closure $isBlocked, Closure $getSnapshot, Closure $unblock, Closure $list): void
    {
        // Arrange
        $store = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());

        // Act - block 'subject' and confirm visibility
        $block($store, 'subject', ['k' => 'v']);

        // Assert - present
        static::assertTrue($isBlocked($store, 'subject'));
        static::assertSame(['k' => 'v'], $getSnapshot($store, 'subject'));
        static::assertFalse($isBlocked($store, 'other'));
        static::assertNull($getSnapshot($store, 'other'));

        // Act - unblock
        $unblock($store, 'subject');

        // Assert - gone from both the snapshot store and the index
        static::assertFalse($isBlocked($store, 'subject'));
        static::assertSame([], $list($store));
    }

    public static function provideAxisCases(): iterable
    {
        yield 'session axis' => [
            static fn(BlockedSessionStore $s, string $k, array $v): mixed => $s->blockSession($k, $v),
            static fn(BlockedSessionStore $s, string $k): bool => $s->isSessionBlocked($k),
            static fn(BlockedSessionStore $s, string $k): ?array => $s->getSessionSnapshot($k),
            static fn(BlockedSessionStore $s, string $k): mixed => $s->unblockSession($k),
            static fn(BlockedSessionStore $s): array => $s->listBlockedSessions(),
        ];
        yield 'ip axis' => [
            static fn(BlockedSessionStore $s, string $k, array $v): mixed => $s->blockIp($k, $v),
            static fn(BlockedSessionStore $s, string $k): bool => $s->isIpBlocked($k),
            static fn(BlockedSessionStore $s, string $k): ?array => $s->getIpSnapshot($k),
            static fn(BlockedSessionStore $s, string $k): mixed => $s->unblockIp($k),
            static fn(BlockedSessionStore $s): array => $s->listBlockedIps(),
        ];
    }

    public function testSessionAndIpNamespacesDoNotCollide(): void
    {
        // Arrange - same key on both axes must remain independent
        $store = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());

        // Act
        $store->blockSession('same', ['axis' => 'session']);
        $store->blockIp('same', ['axis' => 'ip']);

        // Assert
        static::assertSame(['axis' => 'session'], $store->getSessionSnapshot('same'));
        static::assertSame(['axis' => 'ip'], $store->getIpSnapshot('same'));
    }

    public function testUnblockUnknownEntryIsSilentNoOp(): void
    {
        // Arrange
        $store = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());

        // Act & Assert - both should complete without exception
        $store->unblockSession('never-blocked');
        $store->unblockIp('never-blocked');
        static::assertSame([], $store->listBlockedSessions());
        static::assertSame([], $store->listBlockedIps());
    }

    public function testClearAllWipesEverySessionAndIpEntry(): void
    {
        // Arrange
        $store = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());
        $store->blockSession('s1', ['a' => 1]);
        $store->blockSession('s2', ['a' => 2]);
        $store->blockIp('1.1.1.1', ['a' => 3]);
        $store->blockIp('2.2.2.2', ['a' => 4]);

        // Act
        $store->clearAll();

        // Assert
        static::assertSame([], $store->listBlockedSessions());
        static::assertSame([], $store->listBlockedIps());
        static::assertFalse($store->isSessionBlocked('s1'));
        static::assertFalse($store->isIpBlocked('1.1.1.1'));
    }

    public function testListBlockedReturnsKeyAndSnapshotPairs(): void
    {
        // Arrange
        $store = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());
        $store->blockSession('s1', ['primaryProvider' => 'not_found']);
        $store->blockIp('1.2.3.4', ['primaryProvider' => 'fuse']);

        // Act
        $sessions = $store->listBlockedSessions();
        $ips = $store->listBlockedIps();

        // Assert
        static::assertSame([['key' => 's1', 'snapshot' => ['primaryProvider' => 'not_found']]], $sessions);
        static::assertSame([['key' => '1.2.3.4', 'snapshot' => ['primaryProvider' => 'fuse']]], $ips);
    }

    public function testGetSessionBlockExpiresAtReturnsFutureTimestampWithinTtl(): void
    {
        // Arrange
        $store = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());
        $store->blockSession('abc', ['k' => 'v'], 3600);

        // Act
        $expires = $store->getSessionBlockExpiresAt('abc');

        // Assert - within a 10-second tolerance window of `now + 3600`
        static::assertNotNull($expires);
        $delta = $expires->getTimestamp() - time();
        static::assertGreaterThanOrEqual(3590, $delta);
        static::assertLessThanOrEqual(3600, $delta);
    }

    public function testGetBlockExpiresAtReturnsNullForUnknownEntryOnBothAxes(): void
    {
        // Arrange
        $store = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());

        // Act / Assert
        static::assertNull($store->getSessionBlockExpiresAt('never'));
        static::assertNull($store->getIpBlockExpiresAt('never'));
    }

    public function testListPrunesStaleEntriesAndStillReturnsLiveOnes(): void
    {
        // Arrange - plant a fresh entry, then inject an expired index row
        $pool = new ArrayAdapter();
        $store = new BlockedSessionStore($pool, new NullLogger());
        $store->blockSession('fresh', ['k' => 'v']);

        $indexItem = $pool->getItem('security_blocked_sessions_index');
        $index = $indexItem->get();
        static::assertIsArray($index);
        $index['stale'] = time() - 60;
        $indexItem->set($index);
        $pool->save($indexItem);

        // Act
        $entries = $store->listBlockedSessions();

        // Assert - stale dropped, fresh kept
        static::assertSame([['key' => 'fresh', 'snapshot' => ['k' => 'v']]], $entries);
    }

    public function testListPrunesIndexedEntriesWhenTheirSnapshotIsMissing(): void
    {
        // Arrange - index says 'orphan' is live but the snapshot key was evicted
        $pool = new ArrayAdapter();
        $store = new BlockedSessionStore($pool, new NullLogger());
        $store->blockSession('orphan', ['k' => 'v']);
        $pool->deleteItem('security_blocked_session_orphan');

        // Act
        $entries = $store->listBlockedSessions();

        // Assert
        static::assertSame([], $entries);
    }

    public function testAddToIndexPrunesPreviouslyStaleEntries(): void
    {
        // Arrange - plant a stale entry, then block something new
        $pool = new ArrayAdapter();
        $item = $pool->getItem('security_blocked_sessions_index');
        $item->set(['stale' => time() - 60]);
        $pool->save($item);

        $store = new BlockedSessionStore($pool, new NullLogger());

        // Act
        $store->blockSession('fresh', ['k' => 'v']);

        // Assert - stale gone, fresh present
        $entries = $store->listBlockedSessions();
        static::assertSame([['key' => 'fresh', 'snapshot' => ['k' => 'v']]], $entries);
    }

    /**
     * @param mixed $plantedValue value to inject at the cache slot under test
     * @param Closure(BlockedSessionStore): mixed $invoke
     */
    #[DataProvider('provideCorruptedCacheCases')]
    public function testCorruptedCacheValuesDegradeGracefully(string $cacheKey, mixed $plantedValue, Closure $invoke, mixed $expected): void
    {
        // Arrange
        $pool = new ArrayAdapter();
        $item = $pool->getItem($cacheKey);
        $item->set($plantedValue);
        $pool->save($item);
        $store = new BlockedSessionStore($pool, new NullLogger());

        // Act
        $actual = $invoke($store);

        // Assert
        static::assertSame($expected, $actual);
    }

    public static function provideCorruptedCacheCases(): iterable
    {
        yield 'non-array snapshot returns null' => [
            'security_blocked_session_garbage',
            'not-an-array',
            static fn(BlockedSessionStore $s): ?array => $s->getSessionSnapshot('garbage'),
            null,
        ];
        yield 'non-array snapshot is reported as not blocked' => [
            'security_blocked_session_garbage',
            'not-an-array',
            static fn(BlockedSessionStore $s): bool => $s->isSessionBlocked('garbage'),
            false,
        ];
        yield 'non-array index falls back to empty list' => [
            'security_blocked_sessions_index',
            'not-an-index',
            static fn(BlockedSessionStore $s): array => $s->listBlockedSessions(),
            [],
        ];
    }

    public function testLoadIndexFiltersOutEntriesWithUnexpectedShape(): void
    {
        // Arrange - index mixes valid and invalid rows; only valid+matched row survives
        $pool = new ArrayAdapter();
        $item = $pool->getItem('security_blocked_sessions_index');
        $item->set([
            'good' => time() + 3600,
            123 => time() + 3600,
            'bad' => 'not-an-int',
        ]);
        $pool->save($item);
        $snap = $pool->getItem('security_blocked_session_good');
        $snap->set(['k' => 'v']);
        $pool->save($snap);
        $store = new BlockedSessionStore($pool, new NullLogger());

        // Act
        $entries = $store->listBlockedSessions();

        // Assert
        static::assertSame([['key' => 'good', 'snapshot' => ['k' => 'v']]], $entries);
    }

    /**
     * @param array<string> $variants
     */
    #[DataProvider('provideSanitizableKeys')]
    public function testKeySanitizationCollapsesSpecialChars(array $variants): void
    {
        // Arrange - any of the variants should land in the same sanitized cache slot
        $store = new BlockedSessionStore(new ArrayAdapter(), new NullLogger());
        $store->blockSession($variants[0], ['from' => $variants[0]]);

        // Act / Assert
        foreach (array_slice($variants, 1) as $variant) {
            static::assertSame(
                ['from' => $variants[0]],
                $store->getSessionSnapshot($variant),
                sprintf('Variant "%s" should sanitize to the same key as "%s"', $variant, $variants[0]),
            );
        }
    }

    public static function provideSanitizableKeys(): iterable
    {
        yield 'spaces and @' => [['user@host', 'user_host']];
        yield 'slashes' => [['a/b/c', 'a_b_c']];
        yield 'punctuation' => [['x!y?z', 'x_y_z']];
    }

    /**
     * @param Closure(BlockedSessionStore): mixed $invoke
     */
    #[DataProvider('provideThrowingPoolCases')]
    public function testCachePoolExceptionsAreSwallowed(Closure $invoke, mixed $expected): void
    {
        // Arrange - every pool operation throws; the store must absorb and return a safe fallback
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willThrowException(new Exception('boom'));
        $pool->method('deleteItem')->willThrowException(new Exception('boom'));
        $store = new BlockedSessionStore($pool, new NullLogger());

        // Act
        $result = $invoke($store);

        // Assert - the documented fallback per API
        static::assertSame($expected, $result);
    }

    public static function provideThrowingPoolCases(): iterable
    {
        yield 'blockSession swallows pool exception' => [
            static function (BlockedSessionStore $s): ?bool {
                $s->blockSession('abc', ['k' => 'v']);
                return true;
            },
            true,
        ];
        yield 'getSessionSnapshot returns null on pool exception' => [
            static fn(BlockedSessionStore $s): ?array => $s->getSessionSnapshot('abc'),
            null,
        ];
        yield 'isSessionBlocked reports false on pool exception' => [
            static fn(BlockedSessionStore $s): bool => $s->isSessionBlocked('abc'),
            false,
        ];
        yield 'unblockSession swallows pool exception' => [
            static function (BlockedSessionStore $s): bool {
                $s->unblockSession('abc');
                return true;
            },
            true,
        ];
        yield 'unblockIp swallows pool exception' => [
            static function (BlockedSessionStore $s): bool {
                $s->unblockIp('1.2.3.4');
                return true;
            },
            true,
        ];
        yield 'listBlockedSessions returns empty on pool exception' => [
            static fn(BlockedSessionStore $s): array => $s->listBlockedSessions(),
            [],
        ];
        yield 'listBlockedIps returns empty on pool exception' => [
            static fn(BlockedSessionStore $s): array => $s->listBlockedIps(),
            [],
        ];
    }

    public function testReadBlockReturnsNullWhenCacheItemIsNotAHit(): void
    {
        // Arrange - item exists but reports miss (TTL expired between the pool's hit-check and our read)
        $miss = $this->createStub(CacheItemInterface::class);
        $miss->method('isHit')->willReturn(false);
        $pool = $this->createStub(CacheItemPoolInterface::class);
        $pool->method('getItem')->willReturn($miss);
        $store = new BlockedSessionStore($pool, new NullLogger());

        // Act / Assert
        static::assertNull($store->getSessionSnapshot('abc'));
    }
}
