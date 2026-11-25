<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\Service;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Entity\Follower;
use Plugin\Fediverse\Repository\AccountRepository;
use Plugin\Fediverse\Repository\FollowerRepository;
use Plugin\Fediverse\Security\HttpSignature;
use Plugin\Fediverse\Service\AccountService;
use Plugin\Fediverse\Service\ConfigService;
use Plugin\Fediverse\Service\DeliveryQueue;
use Plugin\Fediverse\Service\InboxService;
use Plugin\Fediverse\Service\RemoteKeyService;
use Plugin\Fediverse\ValueObject\Config;
use Plugin\Fediverse\ValueObject\RemoteKey;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\RateLimiter\Storage\InMemoryStorage;

class InboxServiceTest extends TestCase
{
    private const string NOW = '2026-11-25T10:00:00+00:00';
    private const string ACTOR_ID = 'https://social.example/users/alice';
    private const string KEY_ID = self::ACTOR_ID . '#main-key';
    private const string FOLLOW_ID = 'https://social.example/follows/1';

    /** @var list<string> */
    private array $found = [];

    /** @var list<object> */
    private array $removed = [];

    public function testACachedKeyPassesWhileTheFetchLimitIsExhausted(): void
    {
        // Act
        $status = $this->inbox(isCached: true)->receive($this->undo());

        // Assert
        static::assertSame(Response::HTTP_ACCEPTED, $status);
        static::assertCount(1, $this->removed);
    }

    public function testAMissIsRefusedWithoutAFetchOnceTheLimitIsExhausted(): void
    {
        // Act
        $status = $this->inbox(isCached: false)->receive($this->undo());

        // Assert
        static::assertSame(Response::HTTP_TOO_MANY_REQUESTS, $status);
        static::assertSame([], $this->found);
        static::assertSame([], $this->removed);
    }

    private function undo(): Request
    {
        return Request::create(
            'https://events.example/fediverse/inbox',
            'POST',
            server: ['HTTP_SIGNATURE' => 'parsed by the stub', 'REMOTE_ADDR' => '203.0.113.7'],
            content: json_encode(['type' => 'Undo', 'actor' => self::ACTOR_ID, 'object' => self::FOLLOW_ID], JSON_THROW_ON_ERROR),
        );
    }

    private function inbox(bool $isCached): InboxService
    {
        $account = new Account(null, 'events', 'PUBLIC KEY', 'cipher', new DateTimeImmutable('2026-11-01'));
        $follower = new Follower($account, self::ACTOR_ID, self::ACTOR_ID . '/inbox', null, self::FOLLOW_ID, new DateTimeImmutable('2026-11-20'));

        $followers = $this->createStub(FollowerRepository::class);
        $followers->method('findOneByFollowId')->willReturn($follower);

        $httpSignature = $this->createStub(HttpSignature::class);
        $httpSignature->method('parse')->willReturn(['keyId' => self::KEY_ID, 'algorithm' => null, 'headers' => [], 'signature' => 'x']);
        $httpSignature->method('verify')->willReturn(true);

        $remoteKeys = $this->createStub(RemoteKeyService::class);
        $remoteKeys->method('hostOf')->willReturnCallback(static fn(string $url): ?string => parse_url($url, PHP_URL_HOST) ?: null);
        $remoteKeys->method('isCached')->willReturn($isCached);
        $remoteKeys
            ->method('find')
            ->willReturnCallback(function (string $keyId): RemoteKey {
                $this->found[] = $keyId;

                return new RemoteKey($keyId, self::ACTOR_ID, 'PEM', self::ACTOR_ID . '/inbox', null, new DateTimeImmutable(self::NOW)->getTimestamp());
            });

        $configService = $this->createStub(ConfigService::class);
        $configService->method('getCeiling')->willReturn(new Config());

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('remove')->willReturnCallback(function (object $entity): void {
            $this->removed[] = $entity;
        });

        $keyFetchLimiter = new RateLimiterFactory([
            'id' => 'key_fetch',
            'policy' => 'fixed_window',
            'limit' => 1,
            'interval' => '1 minute',
        ], new InMemoryStorage());
        $keyFetchLimiter->create('203.0.113.7')->consume();

        return new InboxService(
            $this->createStub(AccountRepository::class),
            $followers,
            $this->createStub(Actor::class),
            $httpSignature,
            $remoteKeys,
            $configService,
            $this->createStub(DeliveryQueue::class),
            $this->createStub(AccountService::class),
            $em,
            new LockFactory(new InMemoryStore()),
            new MockClock(self::NOW),
            new RateLimiterFactory(['id' => 'inbox', 'policy' => 'fixed_window', 'limit' => 100, 'interval' => '1 minute'], new InMemoryStorage()),
            $keyFetchLimiter,
        );
    }
}
