<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email\Delivery\Provider;

use App\Service\Email\Delivery\EmailDeliveryLogFilter;
use App\Service\Email\Delivery\Provider\SweegoEmailDeliveryProvider;
use DateTimeImmutable;
use Exception;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class SweegoEmailDeliveryProviderTest extends TestCase
{
    private const string API_KEY = 'sk_test_abc';
    private const string DSN = 'sweego+api://sk_test_abc@default';

    /**
     * @param array<string, mixed> $expectedBodySubset
     */
    #[DataProvider('provideFilterToRequestBodyCases')]
    public function testGetLogsBuildsRequestBodyFromFilter(EmailDeliveryLogFilter $filter, array $expectedBodySubset): void
    {
        // Arrange
        $captured = ['url' => null, 'body' => null, 'headers' => null];
        $http = $this->httpClientReturning(['result' => [], 'nb_result_without_offset' => 0], $captured);
        $provider = new SweegoEmailDeliveryProvider($http, new NullLogger(), self::DSN);

        // Act
        $provider->getLogs($filter);

        // Assert
        static::assertSame('https://api.sweego.io/logs/', $captured['url']);
        $body = json_decode($captured['body'], true);
        static::assertIsArray($body);
        foreach ($expectedBodySubset as $key => $value) {
            static::assertArrayHasKey($key, $body);
            static::assertSame($value, $body[$key]);
        }
        static::assertSame(self::API_KEY, $captured['headers']['Api-Key'][0] ?? null);
    }

    public static function provideFilterToRequestBodyCases(): iterable
    {
        yield 'channel + offset + size always present' => [
            new EmailDeliveryLogFilter(offset: 10, size: 25),
            ['channel' => 'email', 'offset' => 10, 'size' => 25],
        ];
        yield 'messageId maps to transaction_id' => [
            new EmailDeliveryLogFilter(messageId: 'msg-1'),
            ['transaction_id' => 'msg-1'],
        ];
        yield 'recipientEmail maps to email_to' => [
            new EmailDeliveryLogFilter(recipientEmail: 'a@b.test'),
            ['email_to' => 'a@b.test'],
        ];
        yield 'statuses array maps to status' => [
            new EmailDeliveryLogFilter(statuses: ['delivered', 'bounced']),
            ['status' => ['delivered', 'bounced']],
        ];
        yield 'since maps to start_date in Y-m-d' => [
            new EmailDeliveryLogFilter(since: new DateTimeImmutable('2026-05-01')),
            ['start_date' => '2026-05-01'],
        ];
        yield 'until maps to end_date in Y-m-d' => [
            new EmailDeliveryLogFilter(until: new DateTimeImmutable('2026-05-12 23:59:59')),
            ['end_date' => '2026-05-12'],
        ];
    }

    public function testGetLogsMapsResponseItemsToEmailDeliveryLog(): void
    {
        // Arrange
        $http = $this->httpClientReturning([
            'result' => [
                [
                    'transaction_id' => 'tx-1',
                    'status' => 'delivered',
                    'email_to' => 'recipient@example.test',
                    'email_creation' => '2026-05-12T10:00:00+00:00',
                    'email_last_update' => '2026-05-12T10:05:00+00:00',
                    'bounce_type' => null,
                    'msp' => 'gmail',
                ],
            ],
            'nb_result_without_offset' => 42,
        ]);
        $provider = new SweegoEmailDeliveryProvider($http, new NullLogger(), self::DSN);

        // Act
        $collection = $provider->getLogs(new EmailDeliveryLogFilter());

        // Assert
        static::assertSame(42, $collection->total);
        static::assertCount(1, $collection->items);
        $log = $collection->items[0];
        static::assertSame('tx-1', $log->messageId);
        static::assertSame('delivered', $log->status);
        static::assertSame('recipient@example.test', $log->recipientEmail);
        static::assertSame('2026-05-12 10:00:00', $log->createdAt->format('Y-m-d H:i:s'));
        static::assertSame('2026-05-12 10:05:00', $log->updatedAt->format('Y-m-d H:i:s'));
        static::assertNull($log->bounceType);
        static::assertSame('gmail', $log->mailboxProvider);
    }

    /**
     * @param array<string, mixed> $rawRow
     */
    #[DataProvider('provideMissingFieldFallbackCases')]
    public function testMapLogAppliesFallbacksForMissingFields(array $rawRow, string $expectedField, mixed $expectedValue): void
    {
        // Arrange
        $http = $this->httpClientReturning(['result' => [$rawRow]]);
        $provider = new SweegoEmailDeliveryProvider($http, new NullLogger(), self::DSN);

        // Act
        $log = $provider->getLogs(new EmailDeliveryLogFilter())->items[0];

        // Assert
        static::assertSame($expectedValue, $log->{$expectedField});
    }

    public static function provideMissingFieldFallbackCases(): iterable
    {
        yield 'transaction_id missing → swg_uid fallback' => [
            ['swg_uid' => 'fallback-uid'],
            'messageId',
            'fallback-uid',
        ];
        yield 'transaction_id and swg_uid both missing → empty string' => [
            [],
            'messageId',
            '',
        ];
        yield 'status missing → unknown' => [
            [],
            'status',
            'unknown',
        ];
        yield 'email_to missing → empty string' => [
            [],
            'recipientEmail',
            '',
        ];
        yield 'bounce_type missing → null' => [
            [],
            'bounceType',
            null,
        ];
        yield 'msp missing → null' => [
            [],
            'mailboxProvider',
            null,
        ];
    }

    public function testGetLogsReturnsEmptyCollectionWhenApiKeyMissing(): void
    {
        // Arrange - DSN with no user component → empty api key → not available
        $callCount = 0;
        $http = new MockHttpClient(static function () use (&$callCount): MockResponse {
            $callCount++;
            return new MockResponse('{}');
        });
        $provider = new SweegoEmailDeliveryProvider($http, new NullLogger(), 'sweego+api://default');

        // Act
        $collection = $provider->getLogs(new EmailDeliveryLogFilter(offset: 5, size: 7));

        // Assert
        static::assertFalse($provider->isAvailable());
        static::assertTrue($collection->isEmpty());
        static::assertSame(0, $collection->total);
        static::assertSame(5, $collection->offset);
        static::assertSame(7, $collection->size);
        static::assertSame(0, $callCount, 'No HTTP request should be made when API key is missing');
    }

    public function testIsAvailableReturnsTrueWhenApiKeyPresent(): void
    {
        // Arrange
        $provider = new SweegoEmailDeliveryProvider(
            new MockHttpClient(),
            new NullLogger(),
            self::DSN,
        );

        // Act / Assert
        static::assertTrue($provider->isAvailable());
    }

    public function testGetLogsReturnsEmptyCollectionWhenHttpThrows(): void
    {
        // Arrange - HTTP client throws on request
        $http = new MockHttpClient(static function (): MockResponse {
            throw new Exception('network down');
        });
        $provider = new SweegoEmailDeliveryProvider($http, new NullLogger(), self::DSN);

        // Act
        $collection = $provider->getLogs(new EmailDeliveryLogFilter(offset: 2, size: 10));

        // Assert - swallowed, empty collection with the requested paging echoed back
        static::assertTrue($collection->isEmpty());
        static::assertSame(0, $collection->total);
        static::assertSame(2, $collection->offset);
        static::assertSame(10, $collection->size);
    }

    public function testGetLogsFallsBackToItemCountWhenTotalKeyMissing(): void
    {
        // Arrange - API response without nb_result_without_offset
        $http = $this->httpClientReturning([
            'result' => [
                ['transaction_id' => 'a', 'status' => 'delivered', 'email_to' => 'x@y.z'],
                ['transaction_id' => 'b', 'status' => 'delivered', 'email_to' => 'x@y.z'],
            ],
        ]);
        $provider = new SweegoEmailDeliveryProvider($http, new NullLogger(), self::DSN);

        // Act
        $collection = $provider->getLogs(new EmailDeliveryLogFilter());

        // Assert - total falls back to item count
        static::assertSame(2, $collection->total);
    }

    public function testGetLogByMessageIdReturnsFirstMatchingLog(): void
    {
        // Arrange
        $http = $this->httpClientReturning([
            'result' => [
                ['transaction_id' => 'tx-only', 'status' => 'delivered', 'email_to' => 'x@y.z'],
            ],
            'nb_result_without_offset' => 1,
        ]);
        $provider = new SweegoEmailDeliveryProvider($http, new NullLogger(), self::DSN);

        // Act
        $log = $provider->getLogByMessageId('tx-only');

        // Assert
        static::assertNotNull($log);
        static::assertSame('tx-only', $log->messageId);
    }

    public function testGetLogByMessageIdReturnsNullWhenApiReturnsNoResults(): void
    {
        // Arrange
        $http = $this->httpClientReturning(['result' => [], 'nb_result_without_offset' => 0]);
        $provider = new SweegoEmailDeliveryProvider($http, new NullLogger(), self::DSN);

        // Act
        $log = $provider->getLogByMessageId('not-found');

        // Assert
        static::assertNull($log);
    }

    public function testConstructorUrlDecodesPercentEncodedApiKey(): void
    {
        // Arrange - api key with reserved chars, percent-encoded in the DSN
        $rawKey = 'sk:test/with@chars';
        $encodedDsn = 'sweego+api://' . rawurlencode($rawKey) . '@default';
        $captured = ['headers' => null];
        $http = $this->httpClientReturning(['result' => [], 'nb_result_without_offset' => 0], $captured);
        $provider = new SweegoEmailDeliveryProvider($http, new NullLogger(), $encodedDsn);

        // Act
        $provider->getLogs(new EmailDeliveryLogFilter());

        // Assert
        static::assertSame($rawKey, $captured['headers']['Api-Key'][0] ?? null);
    }

    /**
     * @param array<string, mixed> $payload
     * @param array{url?: ?string, body?: ?string, headers?: ?array<string, list<string>>} $captured
     */
    private function httpClientReturning(array $payload, ?array &$captured = null): HttpClientInterface
    {
        return new MockHttpClient(
            static function (string $method, string $url, array $options) use ($payload, &$captured): MockResponse {
                if ($captured !== null) {
                    $captured['url'] = $url;
                    if (array_key_exists('body', $captured)) {
                        $captured['body'] = $options['body'] ?? null;
                    }
                    $headers = [];
                    foreach ($options['headers'] ?? [] as $line) {
                        if (!is_string($line)) {
                            continue;
                        }
                        $parts = explode(': ', $line, 2);
                        if (count($parts) === 2) {
                            $headers[$parts[0]][] = $parts[1];
                        }
                    }
                    $captured['headers'] = $headers;
                }
                return new MockResponse((string) json_encode($payload), [
                    'http_code' => 200,
                    'response_headers' => ['Content-Type' => 'application/json'],
                ]);
            },
        );
    }
}
