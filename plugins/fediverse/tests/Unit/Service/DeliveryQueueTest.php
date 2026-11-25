<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\Service;

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
use Plugin\Fediverse\Service\DeliveryQueue;
use Psr\Log\NullLogger;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

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

    private function account(): Account
    {
        return new Account(null, 'events', 'PUBLIC KEY', 'cipher', new DateTimeImmutable('2026-11-01'));
    }

    private function delivery(): Delivery
    {
        return new Delivery($this->account(), self::INBOX, '{"type":"Accept"}', new DateTimeImmutable(self::NOW));
    }

    private function queue(?Delivery $due, MockResponse $response): DeliveryQueue
    {
        $repository = $this->createStub(DeliveryRepository::class);
        $repository->method('claimDue')->willReturn($due === null ? [] : [$due]);

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

        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($response): MockResponse {
            $this->requests[] = [$method, $url, $options];

            return $response;
        });

        return new DeliveryQueue($repository, $httpSignature, $client, $em, $trigger, new MockClock(new DateTimeImmutable(self::NOW)), new NullLogger());
    }
}
