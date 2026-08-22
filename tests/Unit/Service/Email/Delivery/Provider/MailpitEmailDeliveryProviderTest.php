<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email\Delivery\Provider;

use App\Service\Email\Delivery\LogFilter;
use App\Service\Email\Delivery\Provider\MailpitEmailDeliveryProvider;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class MailpitEmailDeliveryProviderTest extends TestCase
{
    private const string DEV_DSN = 'smtp://mailpit:1025';

    #[DataProvider('provideDsnCases')]
    public function testOnlyTheDevCatcherDsnClaimsTheSlot(string $dsn, bool $expected): void
    {
        // Arrange
        $provider = new MailpitEmailDeliveryProvider(new MockHttpClient(), new NullLogger(), $dsn);

        // Act & Assert
        static::assertSame($expected, $provider->isAvailable());
    }

    public static function provideDsnCases(): iterable
    {
        yield 'the dev catcher claims' => ['smtp://mailpit:1025', true];
        yield 'sweego api declines' => ['sweego+api://sk_test_abc@default', false];
        yield 'sweego smtp declines' => ['sweego+smtp://smtp.sweego.io:465', false];
        yield 'a real smtp relay declines' => ['smtp://smtp.example.org:587', false];
        yield 'the null transport declines' => ['null://null', false];
        yield 'an empty dsn declines' => ['', false];
    }

    public function testMessageLookupMapsMailpitPayloadToLog(): void
    {
        // Arrange
        $captured = ['url' => null];
        $http = $this->httpClientReturning([
            'ID' => '4ygB1CvXgfgG9XXKOauin4',
            'MessageID' => '6b983f9e@localhost',
            'To' => [['Name' => '', 'Address' => 'welcome+en@preview.invalid']],
            'Date' => '2026-08-22T13:16:31+02:00',
        ], $captured);
        $provider = new MailpitEmailDeliveryProvider($http, new NullLogger(), self::DEV_DSN);

        // Act
        $log = $provider->getLogByMessageId('4ygB1CvXgfgG9XXKOauin4');

        // Assert
        static::assertSame('http://mailpit:8025/api/v1/message/4ygB1CvXgfgG9XXKOauin4', $captured['url']);
        static::assertSame('4ygB1CvXgfgG9XXKOauin4', $log?->messageId);
        static::assertSame('delivered', $log?->status);
        static::assertSame('welcome+en@preview.invalid', $log?->recipientEmail);
        static::assertSame('mailpit', $log?->mailboxProvider);
        static::assertNull($log?->bounceType);
        static::assertSame('2026-08-22 13:16:31', $log?->createdAt->format('Y-m-d H:i:s'));
    }

    public function testUnknownMessageReturnsNull(): void
    {
        // Arrange
        $http = new MockHttpClient(static fn(): MockResponse => new MockResponse('404 page not found', ['http_code' => 404]));
        $provider = new MailpitEmailDeliveryProvider($http, new NullLogger(), self::DEV_DSN);

        // Act & Assert
        static::assertNull($provider->getLogByMessageId('gone'));
    }

    public function testLookupIsSkippedWhenTheDsnPointsElsewhere(): void
    {
        // Arrange
        $callCount = 0;
        $http = new MockHttpClient(static function () use (&$callCount): MockResponse {
            $callCount++;

            return new MockResponse('{}');
        });
        $provider = new MailpitEmailDeliveryProvider($http, new NullLogger(), 'sweego+api://sk_test_abc@default');

        // Act
        $log = $provider->getLogByMessageId('any-id');
        $collection = $provider->getLogs(new LogFilter(offset: 5, size: 7));

        // Assert
        static::assertNull($log);
        static::assertTrue($collection->isEmpty());
        static::assertSame(5, $collection->offset);
        static::assertSame(7, $collection->size);
        static::assertSame(0, $callCount, 'No HTTP request should be made when another provider owns the DSN');
    }

    public function testSearchBuildsQueryFromFilterAndMapsMessages(): void
    {
        // Arrange
        $captured = ['url' => null];
        $http = $this->httpClientReturning([
            'messages_count' => 121,
            'messages' => [
                [
                    'ID' => 'abc',
                    'To' => [['Address' => 'welcome+de@preview.invalid']],
                    'Created' => '2026-08-22T11:16:31.66Z',
                ],
            ],
        ], $captured);
        $provider = new MailpitEmailDeliveryProvider($http, new NullLogger(), self::DEV_DSN);

        // Act
        $collection = $provider->getLogs(new LogFilter(
            recipientEmail: 'welcome+de@preview.invalid',
            since: new DateTimeImmutable('2026-08-01'),
            until: new DateTimeImmutable('2026-08-22 23:59:59'),
            offset: 10,
            size: 25,
        ));

        // Assert
        static::assertStringContainsString('query=to:welcome%2Bde@preview.invalid', (string) $captured['url']);
        static::assertStringContainsString('after:2026-08-01', (string) $captured['url']);
        static::assertStringContainsString('before:2026-08-22', (string) $captured['url']);
        static::assertStringContainsString('start=10', (string) $captured['url']);
        static::assertStringContainsString('limit=25', (string) $captured['url']);
        static::assertSame(121, $collection->total);
        static::assertSame('abc', $collection->items[0]->messageId);
        static::assertSame('delivered', $collection->items[0]->status);
    }

    public function testMessageIdFilterGoesThroughTheSingleMessageEndpoint(): void
    {
        // Arrange
        $captured = ['url' => null];
        $http = $this->httpClientReturning(['ID' => 'tx-1', 'To' => [['Address' => 'a@b.test']]], $captured);
        $provider = new MailpitEmailDeliveryProvider($http, new NullLogger(), self::DEV_DSN);

        // Act
        $collection = $provider->getLogs(new LogFilter(messageId: 'tx-1'));

        // Assert
        static::assertSame('http://mailpit:8025/api/v1/message/tx-1', $captured['url']);
        static::assertSame(1, $collection->total);
        static::assertSame('tx-1', $collection->items[0]->messageId);
    }

    public function testHttpFailureDegradesToEmptyResults(): void
    {
        // Arrange
        $http = new MockHttpClient(static function (): MockResponse {
            throw new Exception('mailpit is down');
        });
        $provider = new MailpitEmailDeliveryProvider($http, new NullLogger(), self::DEV_DSN);

        // Act
        $collection = $provider->getLogs(new LogFilter(offset: 2, size: 10));

        // Assert
        static::assertNull($provider->getLogByMessageId('tx-1'));
        static::assertTrue($collection->isEmpty());
        static::assertSame(0, $collection->total);
        static::assertSame(2, $collection->offset);
        static::assertSame(10, $collection->size);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{url: string|null} $captured
     */
    private function httpClientReturning(array $payload, array &$captured = []): HttpClientInterface
    {
        return new MockHttpClient(static function (string $method, string $url) use ($payload, &$captured): MockResponse {
            $captured['url'] = $url;

            return new MockResponse(json_encode($payload), ['response_headers' => ['content-type' => 'application/json']]);
        });
    }
}
