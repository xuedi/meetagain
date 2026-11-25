<?php declare(strict_types=1);

namespace Plugin\Fediverse\Service;

use Closure;
use Doctrine\ORM\EntityManagerInterface;
use Plugin\Fediverse\ActivityPub\Actor;
use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Entity\Follower;
use Plugin\Fediverse\Repository\AccountRepository;
use Plugin\Fediverse\Repository\FollowerRepository;
use Plugin\Fediverse\Security\HttpSignature;
use Plugin\Fediverse\ValueObject\RemoteKey;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\RateLimiter\RateLimiterFactoryInterface;

readonly class InboxService
{
    public const int MAX_BODY_BYTES = 262144;

    private const int KEY_REFRESH_AFTER_SECONDS = 60;

    public function __construct(
        private AccountRepository $accounts,
        private FollowerRepository $followers,
        private Actor $actor,
        private HttpSignature $httpSignature,
        private RemoteKeyService $remoteKeys,
        private ConfigService $configService,
        private DeliveryQueue $deliveries,
        private AccountService $accountService,
        private EntityManagerInterface $em,
        private LockFactory $lockFactory,
        private ClockInterface $clock,
        #[Autowire(service: 'limiter.fediverse_inbox')]
        private RateLimiterFactoryInterface $limiter,
    ) {}

    public function receive(Request $request): int
    {
        if ((int) $request->headers->get('content-length') > self::MAX_BODY_BYTES || strlen($request->getContent()) > self::MAX_BODY_BYTES) {
            return Response::HTTP_REQUEST_ENTITY_TOO_LARGE;
        }

        $signature = $this->httpSignature->parse($this->signatureHeader($request));
        $host = $signature === null ? null : $this->remoteKeys->hostOf($signature['keyId']);
        if ($signature === null || $host === null) {
            return Response::HTTP_UNAUTHORIZED;
        }

        if ($this->isBlocked($host)) {
            return Response::HTTP_FORBIDDEN;
        }

        if (!$this->limiter->create($host)->consume()->isAccepted()) {
            return Response::HTTP_TOO_MANY_REQUESTS;
        }

        $activity = json_decode($request->getContent(), true);
        $actorId = is_array($activity) ? $this->idOf($activity['actor'] ?? null) : null;
        if (!is_array($activity) || $actorId === null) {
            return Response::HTTP_BAD_REQUEST;
        }

        if ($this->remoteKeys->hostOf($actorId) !== $host) {
            return Response::HTTP_UNAUTHORIZED;
        }

        $verify = fn(Account $signer): ?RemoteKey => $this->verifiedKey($request, $signature, $actorId, $signer);

        return match ($activity['type'] ?? null) {
            'Follow' => $this->follow($activity, $actorId, $verify),
            'Undo' => $this->undo($activity, $actorId, $verify),
            'Delete' => $this->delete($activity, $actorId, $verify),
            default => Response::HTTP_ACCEPTED,
        };
    }

    /**
     * @param array<mixed> $activity
     * @param Closure(Account): ?RemoteKey $verify
     */
    private function follow(array $activity, string $actorId, Closure $verify): int
    {
        $account = $this->localAccount($this->idOf($activity['object'] ?? null));
        $followId = $this->idOf($activity['id'] ?? null);
        if (!$account instanceof Account || $followId === null || !$this->accountService->isFederating($account)) {
            return Response::HTTP_ACCEPTED;
        }

        $key = $verify($account);
        if (!$key instanceof RemoteKey) {
            return Response::HTTP_UNAUTHORIZED;
        }

        $lock = $this->lockFactory->createLock('fediverse.follower.' . $account->getId());
        $lock->acquire(true);
        try {
            $follower = $this->followers->findOneFor($account, $actorId);
            if ($follower instanceof Follower) {
                $follower->refollow($key->inbox, $key->sharedInbox, $followId);
            } else {
                $this->em->persist(new Follower($account, $actorId, $key->inbox, $key->sharedInbox, $followId, $this->clock->now()));
            }

            $account->lock($this->clock->now());
            $this->em->flush();
        } finally {
            $lock->release();
        }

        $this->deliveries->queue($account, [$key->inbox], $this->accept($account, $followId, $actorId));

        return Response::HTTP_ACCEPTED;
    }

    /**
     * @param array<mixed> $activity
     * @param Closure(Account): ?RemoteKey $verify
     */
    private function undo(array $activity, string $actorId, Closure $verify): int
    {
        $object = $activity['object'] ?? null;
        $follower = null;
        if (is_string($object)) {
            $follower = $this->followers->findOneByFollowId($object, $actorId);
        } elseif (is_array($object) && ($object['type'] ?? null) === 'Follow' && $this->idOf($object['actor'] ?? $actorId) === $actorId) {
            $account = $this->localAccount($this->idOf($object['object'] ?? null));
            $follower = $account instanceof Account ? $this->followers->findOneFor($account, $actorId) : null;
        }

        if (!$follower instanceof Follower) {
            return Response::HTTP_ACCEPTED;
        }

        if (!$verify($follower->getAccount()) instanceof RemoteKey) {
            return Response::HTTP_UNAUTHORIZED;
        }

        $this->em->remove($follower);
        $this->em->flush();

        return Response::HTTP_ACCEPTED;
    }

    /**
     * @param array<mixed> $activity
     * @param Closure(Account): ?RemoteKey $verify
     */
    private function delete(array $activity, string $actorId, Closure $verify): int
    {
        $followers = $this->idOf($activity['object'] ?? null) === $actorId ? $this->followers->findByActorId($actorId) : [];
        if ($followers === []) {
            return Response::HTTP_ACCEPTED;
        }

        $signer = $followers[0]->getAccount();
        $isProven = $verify($signer) instanceof RemoteKey || $this->remoteKeys->isGone($actorId, $signer);
        if (!$isProven) {
            return Response::HTTP_UNAUTHORIZED;
        }

        foreach ($followers as $follower) {
            $this->em->remove($follower);
        }
        $this->em->flush();

        return Response::HTTP_ACCEPTED;
    }

    /** @param array{keyId: string, algorithm: ?string, headers: list<string>, signature: string} $signature */
    private function verifiedKey(Request $request, array $signature, string $actorId, Account $signer): ?RemoteKey
    {
        $key = $this->remoteKeys->find($signature['keyId'], $signer);
        if ($key instanceof RemoteKey && $this->verifies($request, $signature, $actorId, $key)) {
            return $key;
        }

        $isStale = $key instanceof RemoteKey && ($this->clock->now()->getTimestamp() - $key->fetchedAt) > self::KEY_REFRESH_AFTER_SECONDS;
        if (!$isStale) {
            return null;
        }

        $key = $this->remoteKeys->find($signature['keyId'], $signer, refresh: true);

        return $key instanceof RemoteKey && $this->verifies($request, $signature, $actorId, $key) ? $key : null;
    }

    /** @param array{keyId: string, algorithm: ?string, headers: list<string>, signature: string} $signature */
    private function verifies(Request $request, array $signature, string $actorId, RemoteKey $key): bool
    {
        if ($key->ownerId !== $actorId) {
            return false;
        }

        $headers = [];
        foreach ($request->headers->all() as $name => $values) {
            $headers[strtolower($name)] = implode(', ', array_filter($values, is_string(...)));
        }
        $headers['host'] = $request->getHttpHost();

        return $this->httpSignature->verify($signature, $request->getMethod(), $request->getRequestUri(), $headers, $request->getContent(), $key->publicKeyPem);
    }

    private function localAccount(?string $actorId): ?Account
    {
        $username = $actorId === null ? null : $this->actor->usernameOf($actorId);
        $account = $username === null ? null : $this->accounts->findOneByUsername($username);

        return $account instanceof Account && !$account->isRetired() ? $account : null;
    }

    private function isBlocked(string $host): bool
    {
        foreach ($this->configService->getCeiling()->getBlockedServers() as $blocked) {
            if ($host === $blocked || str_ends_with($host, '.' . $blocked)) {
                return true;
            }
        }

        return false;
    }

    private function signatureHeader(Request $request): string
    {
        $signature = $request->headers->get('signature');
        if (is_string($signature)) {
            return $signature;
        }

        $authorization = (string) $request->headers->get('authorization');

        return str_starts_with($authorization, 'Signature ') ? substr($authorization, 10) : '';
    }

    /** @return array<string, mixed> */
    private function accept(Account $account, string $followId, string $followerId): array
    {
        $actorId = $this->actor->id($account);

        return [
            '@context' => 'https://www.w3.org/ns/activitystreams',
            'id' => $actorId . '#accepts/' . hash('xxh128', $followId),
            'type' => 'Accept',
            'actor' => $actorId,
            'object' => ['id' => $followId, 'type' => 'Follow', 'actor' => $followerId, 'object' => $actorId],
        ];
    }

    private function idOf(mixed $value): ?string
    {
        $id = is_array($value) ? $value['id'] ?? null : $value;

        return is_string($id) && $id !== '' && strlen($id) <= Follower::MAX_URL_LENGTH ? $id : null;
    }
}
