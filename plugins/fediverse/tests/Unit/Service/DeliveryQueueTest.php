<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\Service;

use Closure;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Generator;
use Module\Task\Contract\TriggerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Entity\Delivery;
use Plugin\Fediverse\Repository\DeliveryRepository;
use Plugin\Fediverse\Security\HttpSignature;
use Plugin\Fediverse\Service\ConfigService;
use Plugin\Fediverse\Service\DeliveryQueue;
use Plugin\Fediverse\ValueObject\Config;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\HttpClient\ResponseStreamInterface;

class DeliveryQueueTest extends TestCase
{
    private const string NOW = '2026-11-25T10:00:00+00:00';
    private const string INBOX = 'https://social.example/users/alice/inbox';

    /** @var list<object> */
    private array $removed = [];

    /** @var list<object> */
    private array $persisted = [];

    /** @var list<string> */
    private array $triggered = [];

    /** @var list<array{string, string, array<string, mixed>}> */
    private array $requests = [];

    /** @var list<string> */
    private array $events = [];

    private int $claims = 0;
    private bool $hasDue = false;
    private MockClock $clock;
    private Closure $onRequest;

    protected function setUp(): void
    {
        $this->onRequest = static function (): void {};
    }

    public function testAnAcceptedDeliveryIsSentSignedAndRemoved(): void
    {
        // Arrange
        $delivery = $this->delivery();

        // Act
        $summary = $this->queue($delivery, new MockResponse('', ['http_code' => 202]))->drain();

        // Assert
        static::assertSame(['sent' => 1, 'retried' => 0, 'failed' => 0], $summary);
        static::assertSame([$delivery], $this->removed);
        [$method, $url, $options] = $this->requests[0];
        static::assertSame('POST', $method);
        static::assertSame(self::INBOX, $url);
        static::assertSame('{"type":"Accept"}', $options['body']);
        static::assertContains('signature: keyId="test"', $options['headers']);
    }

    /** @return Generator<string, array{MockResponse}> */
    public static function passingFailures(): Generator
    {
        yield 'a server error' => [new MockResponse('', ['http_code' => 503])];
        yield 'too many requests' => [new MockResponse('', ['http_code' => 429])];
        yield 'no answer' => [new MockResponse('', ['error' => 'host unreachable'])];
    }

    #[DataProvider('passingFailures')]
    public function testAPassingFailureIsRetriedAfterAPause(MockResponse $response): void
    {
        // Arrange
        $delivery = $this->delivery();

        // Act
        $summary = $this->queue($delivery, $response)->drain();

        // Assert
        static::assertSame(['sent' => 0, 'retried' => 1, 'failed' => 0], $summary);
        static::assertSame([], $this->removed);
        static::assertSame(1, $delivery->getAttempts());
        static::assertEquals(new DateTimeImmutable(self::NOW)->modify(sprintf('+%d minutes', DeliveryQueue::RETRY_MINUTES[0])), $delivery->getDueAt());
    }

    public function testADeliveryTheServerRefusesIsGivenUpAtOnce(): void
    {
        // Arrange
        $delivery = $this->delivery();

        // Act
        $summary = $this->queue($delivery, new MockResponse('', ['http_code' => 404]))->drain();

        // Assert
        static::assertSame(['sent' => 0, 'retried' => 0, 'failed' => 1], $summary);
        static::assertSame([$delivery], $this->removed);
    }

    public function testADeliveryOutOfAttemptsIsGivenUp(): void
    {
        // Arrange
        $delivery = $this->delivery();
        foreach (DeliveryQueue::RETRY_MINUTES as $ignored) {
            $delivery->retryAt(new DateTimeImmutable(self::NOW));
        }

        // Act
        $summary = $this->queue($delivery, new MockResponse('', ['http_code' => 503]))->drain();

        // Assert
        static::assertSame(['sent' => 0, 'retried' => 0, 'failed' => 1], $summary);
        static::assertSame([$delivery], $this->removed);
    }

    public function testQueueingWritesTheRowAndRingsTheDoorbell(): void
    {
        // Arrange
        $account = $this->account();

        // Act
        $this->queue(null, new MockResponse())->queue($account, [self::INBOX, self::INBOX], ['type' => 'Accept', 'name' => 'Café']);

        // Assert
        static::assertCount(1, $this->persisted);
        $delivery = $this->persisted[0];
        static::assertInstanceOf(Delivery::class, $delivery);
        static::assertSame('{"type":"Accept","name":"Café"}', $delivery->getActivity());
        static::assertSame(self::INBOX, $delivery->getInbox());
        static::assertSame(['fediverse-delivery'], $this->triggered);
    }

    public function testAnInboxOnABlockedServerIsNotQueued(): void
    {
        // Act
        $this->queue(null, new MockResponse(), blocked: ['social.example'])->queue(
            $this->account(),
            [self::INBOX, 'https://good.example/inbox'],
            ['type' => 'Accept'],
        );

        // Assert
        static::assertCount(1, $this->persisted);
        static::assertInstanceOf(Delivery::class, $this->persisted[0]);
        static::assertSame('https://good.example/inbox', $this->persisted[0]->getInbox());
    }

    public function testNothingIsQueuedWhenEveryInboxIsBlocked(): void
    {
        // Act
        $this->queue(null, new MockResponse(), blocked: ['example'])->queue($this->account(), [self::INBOX], ['type' => 'Accept']);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame([], $this->triggered);
    }

    public function testAQueuedDeliveryToAServerBlockedSinceIsGivenUpUnsent(): void
    {
        // Arrange
        $delivery = $this->delivery();

        // Act
        $summary = $this->queue($delivery, new MockResponse('', ['http_code' => 202]), blocked: ['social.example'])->drain();

        // Assert
        static::assertSame(['sent' => 0, 'retried' => 0, 'failed' => 1], $summary);
        static::assertSame([$delivery], $this->removed);
        static::assertSame([], $this->requests);
    }

    public function testABatchSettlesEachDeliveryOnItsOwnStatus(): void
    {
        // Arrange
        $sent = $this->delivery('https://a.example/inbox');
        $retried = $this->delivery('https://b.example/inbox');
        $refused = $this->delivery('https://c.example/inbox');
        $queue = $this->queue([$sent, $retried, $refused], [
            'https://a.example/inbox' => new MockResponse('', ['http_code' => 202]),
            'https://b.example/inbox' => new MockResponse('', ['http_code' => 503]),
            'https://c.example/inbox' => new MockResponse('', ['http_code' => 410]),
        ]);

        // Act
        $summary = $queue->drain();

        // Assert
        static::assertSame(['sent' => 1, 'retried' => 1, 'failed' => 1], $summary);
        static::assertSame([$sent, $refused], $this->removed);
        static::assertSame(1, $retried->getAttempts());
    }

    public function testEveryRequestOfABatchStartsBeforeTheFirstStatusIsRead(): void
    {
        // Arrange
        $batch = [$this->delivery('https://a.example/inbox'), $this->delivery('https://b.example/inbox'), $this->delivery('https://c.example/inbox')];

        // Act
        $this->queue($batch, new MockResponse('', ['http_code' => 202]))->drain();

        // Assert
        static::assertSame(['start', 'start', 'start', 'status', 'status', 'status'], $this->events);
    }

    public function testEachRequestCarriesTheDeliveryBudget(): void
    {
        // Act
        $this->queue($this->delivery(), new MockResponse('', ['http_code' => 202]))->drain();

        // Assert
        [, , $options] = $this->requests[0];
        static::assertEqualsWithDelta(DeliveryQueue::SEND_TIMEOUT_SECONDS, $options['timeout'], 0.001);
        static::assertEqualsWithDelta(DeliveryQueue::SEND_MAX_DURATION_SECONDS, $options['max_duration'], 0.001);
    }

    public function testFullBatchesAreClaimedUntilTheCountBudgetIsUsed(): void
    {
        // Arrange
        $queue = $this->queue($this->fullBatch(...), new MockResponse('', ['http_code' => 202]));

        // Act
        $summary = $queue->drain();

        // Assert
        static::assertSame(intdiv(DeliveryQueue::TICK_BUDGET, DeliveryQueue::BATCH_SIZE), $this->claims);
        static::assertSame(DeliveryQueue::TICK_BUDGET, $summary['sent']);
    }

    public function testARunStopsClaimingOnceItsTimeIsSpent(): void
    {
        // Arrange
        $queue = $this->queue($this->fullBatch(...), new MockResponse('', ['http_code' => 202]));
        $this->onRequest = function (): void {
            $this->clock->sleep(DeliveryQueue::RUN_SECONDS / DeliveryQueue::BATCH_SIZE);
        };

        // Act
        $summary = $queue->drain();

        // Assert
        static::assertSame(1, $this->claims);
        static::assertSame(DeliveryQueue::BATCH_SIZE, $summary['sent']);
    }

    public function testARunThatLeavesDeliveriesDueRunsAgainAtOnce(): void
    {
        // Arrange
        $this->hasDue = true;

        // Act
        $this->queue($this->delivery(), new MockResponse('', ['http_code' => 202]))->run();

        // Assert
        static::assertSame(['fediverse-delivery'], $this->triggered);
    }

    public function testARunThatLeavesNothingDueDoesNotRunAgain(): void
    {
        // Act
        $this->queue($this->delivery(), new MockResponse('', ['http_code' => 202]))->run();

        // Assert
        static::assertSame([], $this->triggered);
    }

    public function testNoClaimOutlivesItsLease(): void
    {
        // Act + Assert
        static::assertGreaterThan(DeliveryQueue::RUN_SECONDS + DeliveryQueue::SEND_MAX_DURATION_SECONDS, DeliveryQueue::LEASE_SECONDS);
    }

    private function recording(HttpClientInterface $inner): HttpClientInterface
    {
        $record = function (string $event): void {
            $this->events[] = $event;
        };

        return new readonly class($inner, $record) implements HttpClientInterface {
            /** @param Closure(string): void $record */
            public function __construct(
                private HttpClientInterface $inner,
                private Closure $record,
            ) {}

            public function request(string $method, string $url, array $options = []): ResponseInterface
            {
                ($this->record)('start');

                return new readonly class($this->inner->request($method, $url, $options), $this->record) implements ResponseInterface {
                    /** @param Closure(string): void $record */
                    public function __construct(
                        private ResponseInterface $inner,
                        private Closure $record,
                    ) {}

                    public function getStatusCode(): int
                    {
                        ($this->record)('status');

                        return $this->inner->getStatusCode();
                    }

                    public function getHeaders(bool $throw = true): array
                    {
                        return $this->inner->getHeaders($throw);
                    }

                    public function getContent(bool $throw = true): string
                    {
                        return $this->inner->getContent($throw);
                    }

                    public function toArray(bool $throw = true): array
                    {
                        return $this->inner->toArray($throw);
                    }

                    public function cancel(): void
                    {
                        $this->inner->cancel();
                    }

                    public function getInfo(?string $type = null): mixed
                    {
                        return $this->inner->getInfo($type);
                    }
                };
            }

            public function stream(ResponseInterface|iterable $responses, ?float $timeout = null): ResponseStreamInterface
            {
                return $this->inner->stream($responses, $timeout);
            }

            public function withOptions(array $options): static
            {
                return new static($this->inner->withOptions($options), $this->record);
            }
        };
    }

    /** @return list<Delivery> */
    private function fullBatch(): array
    {
        return array_map(fn(int $i): Delivery => $this->delivery(sprintf('https://s%d.example/inbox', $i)), range(1, DeliveryQueue::BATCH_SIZE));
    }

    private function account(): Account
    {
        return new Account(null, 'events', 'PUBLIC KEY', 'cipher', new DateTimeImmutable('2026-11-01'));
    }

    private function delivery(string $inbox = self::INBOX): Delivery
    {
        return new Delivery($this->account(), $inbox, '{"type":"Accept"}', new DateTimeImmutable(self::NOW));
    }

    /**
     * @param Delivery|list<Delivery>|(Closure(): list<Delivery>)|null $due
     * @param MockResponse|array<string, MockResponse>               $response
     * @param list<string>                                           $blocked
     */
    private function queue(Delivery|array|Closure|null $due, MockResponse|array $response, array $blocked = []): DeliveryQueue
    {
        $this->clock = new MockClock(new DateTimeImmutable(self::NOW));
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getCeiling')->willReturn(new Config()->setBlockedServers($blocked));

        $repository = $this->createStub(DeliveryRepository::class);
        $repository
            ->method('claimDue')
            ->willReturnCallback(function () use ($due): array {
                ++$this->claims;
                if ($due instanceof Closure) {
                    return $due();
                }

                return $this->claims > 1 || $due === null ? [] : ($due instanceof Delivery ? [$due] : $due);
            });
        $repository->method('hasDue')->willReturnCallback(fn(): bool => $this->hasDue);

        $httpSignature = $this->createStub(HttpSignature::class);
        $httpSignature->method('sign')->willReturn(['signature' => 'keyId="test"']);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('remove')->willReturnCallback(function (object $entity): void {
            $this->removed[] = $entity;
        });
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        $trigger = $this->createStub(TriggerInterface::class);
        $trigger
            ->method('runSoon')
            ->willReturnCallback(function (string $identifier): void {
                $this->triggered[] = $identifier;
            });

        $mock = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $this->requests[] = [$method, $url, $options];
            ($this->onRequest)();

            return is_array($response) ? $response[$url] : clone $response;
        });
        $client = $this->recording($mock);

        return new DeliveryQueue($repository, $httpSignature, $client, $em, $configService, $trigger, $this->clock, new NullLogger());
    }
}
