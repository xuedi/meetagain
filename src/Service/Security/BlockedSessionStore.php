<?php

declare(strict_types=1);

namespace App\Service\Security;

use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Throwable;

readonly class BlockedSessionStore
{
    public const int DEFAULT_TTL_SECONDS = 14_400;

    private const string SESSION_INDEX_KEY = 'security_blocked_sessions_index';
    private const string IP_INDEX_KEY = 'security_blocked_ips_index';

    public function __construct(
        private CacheItemPoolInterface $securityCachePool,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $reportSnapshot
     */
    public function blockSession(
        string $sessionId,
        array $reportSnapshot,
        int $ttlSeconds = self::DEFAULT_TTL_SECONDS,
    ): void {
        $this->writeBlock($this->sessionKey($sessionId), $reportSnapshot, $ttlSeconds);
        $this->addToIndex(self::SESSION_INDEX_KEY, $sessionId, $ttlSeconds);
    }

    /**
     * @param array<string, mixed> $reportSnapshot
     */
    public function blockIp(string $ip, array $reportSnapshot, int $ttlSeconds = self::DEFAULT_TTL_SECONDS): void
    {
        $this->writeBlock($this->ipKey($ip), $reportSnapshot, $ttlSeconds);
        $this->addToIndex(self::IP_INDEX_KEY, $ip, $ttlSeconds);
    }

    public function isSessionBlocked(string $sessionId): bool
    {
        return $this->getSessionSnapshot($sessionId) !== null;
    }

    public function isIpBlocked(string $ip): bool
    {
        return $this->getIpSnapshot($ip) !== null;
    }

    public function unblockSession(string $sessionId): void
    {
        $this->deleteKey($this->sessionKey($sessionId));
        $this->removeFromIndex(self::SESSION_INDEX_KEY, $sessionId);
    }

    public function unblockIp(string $ip): void
    {
        $this->deleteKey($this->ipKey($ip));
        $this->removeFromIndex(self::IP_INDEX_KEY, $ip);
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getSessionSnapshot(string $sessionId): ?array
    {
        return $this->readBlock($this->sessionKey($sessionId));
    }

    /**
     * @return array<string, mixed>|null
     */
    public function getIpSnapshot(string $ip): ?array
    {
        return $this->readBlock($this->ipKey($ip));
    }

    /**
     * @return list<array{key: string, snapshot: array<string, mixed>}>
     */
    public function listBlockedSessions(): array
    {
        return $this->listFromIndex(self::SESSION_INDEX_KEY, $this->getSessionSnapshot(...));
    }

    /**
     * @return list<array{key: string, snapshot: array<string, mixed>}>
     */
    public function listBlockedIps(): array
    {
        return $this->listFromIndex(self::IP_INDEX_KEY, $this->getIpSnapshot(...));
    }

    /**
     * @param array<string, mixed> $snapshot
     */
    private function writeBlock(string $cacheKey, array $snapshot, int $ttlSeconds): void
    {
        try {
            $item = $this->securityCachePool->getItem($cacheKey);
            $item->set($snapshot);
            $item->expiresAfter($ttlSeconds);
            $this->securityCachePool->save($item);
        } catch (Throwable $e) {
            $this->logger->warning('Failed to write block snapshot: ' . $e->getMessage(), [
                'exception' => $e,
                'cacheKey' => $cacheKey,
            ]);
        }
    }

    /**
     * @return array<string, mixed>|null
     */
    private function readBlock(string $cacheKey): ?array
    {
        try {
            $item = $this->securityCachePool->getItem($cacheKey);
            if (!$item->isHit()) {
                return null;
            }
            $value = $item->get();
            return is_array($value) ? $value : null;
        } catch (Throwable $e) {
            $this->logger->warning('Failed to read block snapshot: ' . $e->getMessage(), [
                'exception' => $e,
                'cacheKey' => $cacheKey,
            ]);
            return null;
        }
    }

    private function deleteKey(string $cacheKey): void
    {
        try {
            $this->securityCachePool->deleteItem($cacheKey);
        } catch (Throwable $e) {
            $this->logger->warning('Failed to delete block key: ' . $e->getMessage(), [
                'exception' => $e,
                'cacheKey' => $cacheKey,
            ]);
        }
    }

    private function addToIndex(string $indexKey, string $entry, int $ttlSeconds): void
    {
        $index = $this->loadIndex($indexKey);
        $now = time();
        $index[$entry] = $now + $ttlSeconds;
        foreach ($index as $key => $expiresAt) {
            if ($expiresAt >= $now) {
                continue;
            }

            unset($index[$key]);
        }
        $this->saveIndex($indexKey, $index);
    }

    private function removeFromIndex(string $indexKey, string $entry): void
    {
        $index = $this->loadIndex($indexKey);
        if (!array_key_exists($entry, $index)) {
            return;
        }
        unset($index[$entry]);
        $this->saveIndex($indexKey, $index);
    }

    /**
     * @return array<string, int>
     */
    private function loadIndex(string $indexKey): array
    {
        try {
            $item = $this->securityCachePool->getItem($indexKey);
            $value = $item->isHit() ? $item->get() : [];
        } catch (Throwable $e) {
            $this->logger->warning('Failed to load block index: ' . $e->getMessage(), [
                'exception' => $e,
                'indexKey' => $indexKey,
            ]);
            return [];
        }

        if (!is_array($value)) {
            return [];
        }

        $result = [];
        foreach ($value as $key => $expiresAt) {
            if (!(is_string($key) && is_int($expiresAt))) {
                continue;
            }

            $result[$key] = $expiresAt;
        }

        return $result;
    }

    /**
     * @param array<string, int> $index
     */
    private function saveIndex(string $indexKey, array $index): void
    {
        try {
            $item = $this->securityCachePool->getItem($indexKey);
            $item->set($index);
            $item->expiresAfter(self::DEFAULT_TTL_SECONDS * 2);
            $this->securityCachePool->save($item);
        } catch (Throwable $e) {
            $this->logger->warning('Failed to save block index: ' . $e->getMessage(), [
                'exception' => $e,
                'indexKey' => $indexKey,
            ]);
        }
    }

    /**
     * @param callable(string): ?array<string, mixed> $loader
     * @return list<array{key: string, snapshot: array<string, mixed>}>
     */
    private function listFromIndex(string $indexKey, callable $loader): array
    {
        $index = $this->loadIndex($indexKey);
        $now = time();
        $result = [];
        $changed = false;
        foreach ($index as $entry => $expiresAt) {
            if ($expiresAt < $now) {
                unset($index[$entry]);
                $changed = true;
                continue;
            }
            $snapshot = $loader($entry);
            if ($snapshot === null) {
                unset($index[$entry]);
                $changed = true;
                continue;
            }
            $result[] = ['key' => $entry, 'snapshot' => $snapshot];
        }
        if ($changed) {
            $this->saveIndex($indexKey, $index);
        }

        return $result;
    }

    private function sessionKey(string $sessionId): string
    {
        return 'security_blocked_session_' . $this->sanitize($sessionId);
    }

    private function ipKey(string $ip): string
    {
        return 'security_blocked_ip_' . $this->sanitize($ip);
    }

    private function sanitize(string $raw): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $raw) ?? 'unknown';
        return $sanitized === '' ? 'unknown' : $sanitized;
    }
}
