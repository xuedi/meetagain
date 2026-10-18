<?php declare(strict_types=1);

namespace Module\Task\Tests\Unit\Internal;

use InvalidArgumentException;
use Module\Task\Internal\StreamTransportFactory;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class StreamTransportFactoryTest extends TestCase
{
    #[DataProvider('provideUrls')]
    public function testTheRedisUrlBecomesAStreamDsnOnTheSameDatabase(string $url, string $expected): void
    {
        // Arrange
        $factory = new StreamTransportFactory();

        // Act
        $dsn = $factory->toRedisDsn($url);

        // Assert
        self::assertSame(sprintf($expected, rawurlencode((string) gethostname())), $dsn);
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function provideUrls(): iterable
    {
        yield 'host only' => ['redis://valkey:6379', 'redis://valkey:6379/tasks/workers/%s'];
        yield 'database index' => ['redis://valkey:6379/3', 'redis://valkey:6379/tasks/workers/%s?dbindex=3'];
        yield 'password and query' => ['redis://:secret@valkey/2?timeout=5', 'redis://:secret@valkey/tasks/workers/%s?timeout=5&dbindex=2'];
    }

    public function testOnlyItsOwnSchemeIsClaimed(): void
    {
        // Arrange
        $factory = new StreamTransportFactory();

        // Act
        $own = $factory->supports('task-stream:redis://valkey:6379', []);
        $plain = $factory->supports('redis://valkey:6379', []);

        // Assert
        self::assertTrue($own);
        self::assertFalse($plain);
    }

    public function testAUrlWithoutHostIsRefused(): void
    {
        // Arrange
        $factory = new StreamTransportFactory();

        // Assert
        $this->expectException(InvalidArgumentException::class);

        // Act
        $factory->toRedisDsn('not a url');
    }
}
