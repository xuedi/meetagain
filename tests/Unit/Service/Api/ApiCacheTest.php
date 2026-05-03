<?php declare(strict_types=1);

namespace App\Tests\Unit\Service\Api;

use App\Service\Api\ApiCache;
use App\Service\Config\ActivePluginListInterface;
use Override;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;

/**
 * @covers \App\Service\Api\ApiCache
 */
final class ApiCacheTest extends TestCase
{
    public function testCachesGetRequests(): void
    {
        // Arrange
        $cache = new ApiCache(new ArrayAdapter(), new CacheActivePluginList(['glossary']));
        $request = Request::create('https://platform.test/api/glossary', 'GET');
        $calls = 0;
        $compute = function () use (&$calls): array {
            ++$calls;

            return ['count' => $calls];
        };

        // Act
        $first = $cache->getJson($request, 'glossary.list', 60, ['locale' => 'en'], $compute);
        $second = $cache->getJson($request, 'glossary.list', 60, ['locale' => 'en'], $compute);

        // Assert
        static::assertSame(['count' => 1], $first);
        static::assertSame(['count' => 1], $second);
        static::assertSame(1, $calls, 'compute must run only on cache miss');
    }

    public function testBypassesCacheForNonGetMethods(): void
    {
        // Arrange
        $cache = new ApiCache(new ArrayAdapter(), new CacheActivePluginList([]));
        $request = Request::create('https://platform.test/api/glossary', 'POST');
        $calls = 0;
        $compute = function () use (&$calls): array {
            ++$calls;

            return ['attempt' => $calls];
        };

        // Act
        $cache->getJson($request, 'k', 60, [], $compute);
        $cache->getJson($request, 'k', 60, [], $compute);

        // Assert
        static::assertSame(2, $calls, 'POST/PUT/DELETE must never be cached');
    }

    public function testBypassesCacheWhenAuthorizationHeaderPresent(): void
    {
        // Arrange
        $cache = new ApiCache(new ArrayAdapter(), new CacheActivePluginList([]));
        $request = Request::create('https://platform.test/api/events', 'GET');
        $request->headers->set('Authorization', 'Bearer xyz');
        $calls = 0;
        $compute = function () use (&$calls): array {
            ++$calls;

            return ['v' => $calls];
        };

        // Act
        $cache->getJson($request, 'events.list', 60, [], $compute);
        $cache->getJson($request, 'events.list', 60, [], $compute);

        // Assert
        static::assertSame(2, $calls, 'Bearer-authenticated requests must bypass the cache');
    }

    public function testCacheKeyVariesByHost(): void
    {
        // Arrange — same endpoint key but different hosts must produce isolated cache entries.
        $shared = new ArrayAdapter();
        $cache = new ApiCache($shared, new CacheActivePluginList(['glossary']));
        $hostARequest = Request::create('https://host-a.test/api/glossary', 'GET');
        $hostBRequest = Request::create('https://host-b.test/api/glossary', 'GET');
        $calls = 0;
        $compute = function () use (&$calls): array {
            ++$calls;

            return ['n' => $calls];
        };

        // Act
        $hostA = $cache->getJson($hostARequest, 'glossary.list', 60, [], $compute);
        $hostB = $cache->getJson($hostBRequest, 'glossary.list', 60, [], $compute);

        // Assert
        static::assertSame(['n' => 1], $hostA);
        static::assertSame(['n' => 2], $hostB);
    }

    public function testCacheKeyVariesByActivePluginSet(): void
    {
        // Arrange — same host, but different active plugin sets must produce separate cache entries.
        $shared = new ArrayAdapter();
        $broaderSet = new ApiCache($shared, new CacheActivePluginList(['alpha', 'glossary']));
        $narrowerSet = new ApiCache($shared, new CacheActivePluginList(['alpha']));
        $request = Request::create('https://shared.test/api/example', 'GET');
        $calls = 0;
        $compute = function () use (&$calls): array {
            ++$calls;

            return ['n' => $calls];
        };

        // Act
        $broaderSet->getJson($request, 'example.list', 60, [], $compute);
        $narrowerSet->getJson($request, 'example.list', 60, [], $compute);

        // Assert
        static::assertSame(2, $calls, 'Different active plugin sets must not share cache entries');
    }

    public function testCacheKeyVariesByVaryParams(): void
    {
        // Arrange
        $cache = new ApiCache(new ArrayAdapter(), new CacheActivePluginList([]));
        $request = Request::create('https://platform.test/api/events', 'GET');
        $calls = 0;
        $compute = function () use (&$calls): array {
            ++$calls;

            return ['n' => $calls];
        };

        // Act
        $cache->getJson($request, 'events.list', 60, ['locale' => 'en'], $compute);
        $cache->getJson($request, 'events.list', 60, ['locale' => 'de'], $compute);
        $cache->getJson($request, 'events.list', 60, ['locale' => 'en'], $compute);

        // Assert
        static::assertSame(2, $calls, 'Different vary params produce separate cache entries; identical ones reuse');
    }

    public function testVaryParamOrderDoesNotAffectKey(): void
    {
        // Arrange — ksort canonicalisation: the compute function should run once.
        $cache = new ApiCache(new ArrayAdapter(), new CacheActivePluginList([]));
        $request = Request::create('https://platform.test/api/events', 'GET');
        $calls = 0;
        $compute = function () use (&$calls): array {
            ++$calls;

            return [];
        };

        // Act
        $cache->getJson($request, 'k', 60, ['a' => 1, 'b' => 2], $compute);
        $cache->getJson($request, 'k', 60, ['b' => 2, 'a' => 1], $compute);

        // Assert
        static::assertSame(1, $calls);
    }
}

final readonly class CacheActivePluginList implements ActivePluginListInterface
{
    /**
     * @param array<string> $keys
     */
    public function __construct(private array $keys) {}

    #[Override] public function getActiveList(): array
    {
        return $this->keys;
    }
}
