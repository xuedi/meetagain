<?php declare(strict_types=1);

namespace Tests\Unit\Service\Cache;

use App\Service\Cache\RedisService;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Redis;

class RedisServiceTest extends TestCase
{
    public function testKeysAreGroupedByTheirPrefixAcrossScanBatches(): void
    {
        // Arrange
        $service = $this->service([
            ['ma:event_12' => [Redis::REDIS_STRING, 'x'], 'ma:event_9' => [Redis::REDIS_STRING, 'y']],
            ['ma:cms.page' => [Redis::REDIS_STRING, 'z'], 'PHPREDIS_SESSION:abc' => [Redis::REDIS_STRING, 's']],
        ]);

        // Act
        $prefixes = $service->listPrefixes();

        // Assert
        static::assertSame(['event' => 2, 'cms' => 1, 'abc' => 1], $prefixes);
    }

    public function testListingOnePrefixReturnsItsKeysSortedWithTheirMetadata(): void
    {
        // Arrange
        $service = $this->service([
            ['ma:event_9' => [Redis::REDIS_SET, ['a'], 60], 'ma:cms.page' => [Redis::REDIS_STRING, 'z']],
            ['ma:event_12' => [Redis::REDIS_HASH, ['f' => 'v'], -1]],
        ]);

        // Act
        $result = $service->listKeys('event');

        // Assert
        static::assertSame(['ma:event_12', 'ma:event_9'], array_column($result['keys'], 'key'));
        static::assertSame(['hash', 'set'], array_column($result['keys'], 'type'));
        static::assertSame([-1, 60], array_column($result['keys'], 'ttl'));
        static::assertSame(3, $result['totalScanned']);
        static::assertFalse($result['truncated']);
    }

    public function testListingStopsAtTheLimitAndSaysSo(): void
    {
        // Arrange
        $service = $this->service([['a:one' => [Redis::REDIS_STRING, '1'], 'a:two' => [Redis::REDIS_STRING, '2'], 'a:three' => [Redis::REDIS_STRING, '3']]]);

        // Act
        $result = $service->listKeys(null, 2);

        // Assert
        static::assertCount(2, $result['keys']);
        static::assertTrue($result['truncated']);
    }

    public function testAMissingKeyInspectsAsNull(): void
    {
        // Arrange
        $service = $this->service([[]]);

        // Act
        $result = $service->inspect('gone');

        // Assert
        static::assertNull($result);
    }

    /** @return iterable<string, array{int, mixed, string, string}> */
    public static function valueTypeProvider(): iterable
    {
        yield 'list' => [Redis::REDIS_LIST, ['first', 'second'], 'list', "first\nsecond"];
        yield 'set' => [Redis::REDIS_SET, ['only'], 'set', 'only'];
        yield 'sorted set' => [Redis::REDIS_ZSET, ['top' => 2.0], 'zset', 'top  (2)'];
        yield 'hash' => [Redis::REDIS_HASH, ['field' => 'value'], 'hash', 'field = value'];
        yield 'binary member' => [Redis::REDIS_SET, ["\xff\xfe"], 'set', 'fffe'];
    }

    #[DataProvider('valueTypeProvider')]
    public function testEveryValueTypeIsRenderedAsReadableText(int $type, mixed $value, string $label, string $expected): void
    {
        // Arrange
        $service = $this->service([['k' => [$type, $value]]]);

        // Act
        $result = $service->inspect('k');

        // Assert
        static::assertSame($label, $result['type'] ?? null);
        static::assertSame($expected, $result['value'] ?? null);
    }

    /** @return iterable<string, array{string, string|null}> */
    public static function decodableProvider(): iterable
    {
        yield 'json is pretty printed' => ['{"a":1}', "{\n    \"a\": 1\n}"];
        yield 'serialized php is exported' => [serialize(['a' => 1]), var_export(['a' => 1], true)];
        yield 'serialized false is exported' => ['b:0;', 'false'];
        yield 'plain text is left alone' => ['just text', null];
        yield 'an empty value has nothing to decode' => ['', null];
    }

    #[DataProvider('decodableProvider')]
    public function testAStringValueIsDecodedWhenItCanBe(string $raw, ?string $decoded): void
    {
        // Arrange
        $service = $this->service([['k' => [Redis::REDIS_STRING, $raw]]]);

        // Act
        $result = $service->inspect('k');

        // Assert
        static::assertSame($decoded, $result['decoded'] ?? null);
    }

    public function testALongValueIsCutToThePreviewSize(): void
    {
        // Arrange
        $service = $this->service([['k' => [Redis::REDIS_STRING, str_repeat('x', 20_000)]]]);

        // Act
        $result = $service->inspect('k');

        // Assert
        static::assertSame(16_384, strlen($result['value'] ?? ''));
    }

    /**
     * @param list<array<string, array{0: int, 1: mixed, 2?: int}>> $batches
     */
    private function service(array $batches): RedisService
    {
        $store = array_merge(...$batches);
        $redis = $this->createStub(Redis::class);
        $redis
            ->method('scan')
            ->willReturnCallback(static function (?int &$iterator) use ($batches): array|false {
                $index = $iterator ?? 0;
                $iterator = ($index + 1) < count($batches) ? $index + 1 : 0;

                return array_key_exists($index, $batches) ? array_keys($batches[$index]) : false;
            });
        $redis->method('type')->willReturnCallback(static fn(string $key): int => $store[$key][0] ?? Redis::REDIS_NOT_FOUND);
        $redis->method('ttl')->willReturnCallback(static fn(string $key): int => $store[$key][2] ?? -1);
        $redis->method('rawCommand')->willReturn(64);
        $redis->method('get')->willReturnCallback(static fn(string $key): mixed => $store[$key][1] ?? false);
        $redis->method('lRange')->willReturnCallback(static fn(string $key): mixed => $store[$key][1]);
        $redis->method('sMembers')->willReturnCallback(static fn(string $key): mixed => $store[$key][1]);
        $redis->method('zRange')->willReturnCallback(static fn(string $key): mixed => $store[$key][1]);
        $redis->method('hGetAll')->willReturnCallback(static fn(string $key): mixed => $store[$key][1]);

        return new RedisService($redis);
    }
}
