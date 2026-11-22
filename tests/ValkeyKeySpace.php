<?php declare(strict_types=1);

namespace Tests;

use Redis;
use Symfony\Component\Cache\Adapter\RedisAdapter;

final class ValkeyKeySpace
{
    public static function configure(Redis $redis): void
    {
        $redis->setOption(Redis::OPT_PREFIX, self::prefix());
    }

    public static function clear(string $redisUrl): void
    {
        $redis = RedisAdapter::createConnection($redisUrl);
        assert($redis instanceof Redis);
        $redis->setOption(Redis::OPT_SCAN, Redis::SCAN_RETRY);
        $pattern = addcslashes(self::prefix(), '*?[]\\') . '*';
        $iterator = null;
        do {
            $keys = $redis->scan($iterator, $pattern, 1000);
            if (is_array($keys) && $keys !== []) {
                $redis->unlink($keys);
            }
        } while ($iterator > 0);
        $redis->close();
    }

    private static function prefix(): string
    {
        $token = $_SERVER['TEST_TOKEN'] ?? getenv('TEST_TOKEN');

        return 'test' . (is_string($token) ? $token : '') . ':';
    }
}
