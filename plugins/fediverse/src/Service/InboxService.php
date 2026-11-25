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
        private RateLimiterFactoryInterface $inboxLimiter,
        #[Autowire(service: 'limiter.fediverse_key_fetch')]
        private RateLimiterFactoryInterface $keyFetchLimiter,
    ) {}

    public function receive(Request $request): int
    {
        $announcedLength = (int) $request->headers->get('content-length');
        $bodyLength = strlen($request->getContent());
        if ($announcedLength > self::MAX_BODY_BYTES || $bodyLength > self::MAX_BODY_BYTES) {
            return Response::HTTP_REQUEST_ENTITY_TOO_LARGE;
        }

        $signature = $this->httpSignature->parse($this->signatureHeader($request));
        $host = $signature === null ? null : $this->remoteKeys->hostOf($signature['keyId']);
        if ($signature === null || $host === null) {
            return Response::HTTP_UNAUTHORIZED;
        }

        if ($this->configService->getCeiling()->blocks($host)) {
            return Response::HTTP_FORBIDDEN;
        }

        if (!$this->inboxLimiter->create($host)->consume()->isAccepted()) {
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

        $verify = fn(Account $signer): RemoteKey|int => $this->verifiedKey($request, $signature, $actorId, $signer);

        return match ($activity['type'] ?? null) {
            'Follow' => $this->follow($activity, $actorId, $verify),
            'Undo' => $this->undo($activity, $actorId, $verify),
            'Delete' => $this->delete($request, $activity, $actorId, $verify),
            default => Response::HTTP_ACCEPTED,
        };
    }

    /**
     * @param array<mixed> $activity
     * @param Closure(Account): (RemoteKey|int) $verify
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
            return $key;
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
     * @param Closure(Account): (RemoteKey|int) $verify
     */
    private function undo(array $activity, string $actorId, Closure $verify): int
    {
        $object = $activity['object'] ?? null;
        $follower = null;
        if (is_string($object)) {
            $follower = $this->followers->findOneByFollowId($object, $actorId);
        } elseif (is_array($object) && ($object['type'] ?? null) === 'Follow') {
            $followerId = $this->idOf($object['actor'] ?? $actorId);
            $account = $followerId === $actorId ? $this->localAccount($this->idOf($object['object'] ?? null)) : null;
            $follower = $account instanceof Account ? $this->followers->findOneFor($account, $actorId) : null;
        }

        if (!$follower instanceof Follower) {
            return Response::HTTP_ACCEPTED;
        }

        $key = $verify($follower->getAccount());
        if (!$key instanceof RemoteKey) {
            return $key;
        }

        $this->em->remove($follower);
        $this->em->flush();

        return Response::HTTP_ACCEPTED;
    }

    /**
     * @param array<mixed> $activity
     * @param Closure(Account): (RemoteKey|int) $verify
     */
    private function delete(Request $request, array $activity, string $actorId, Closure $verify): int
    {
        $isSelf = $this->idOf($activity['object'] ?? null) === $actorId;
        $followers = $isSelf ? $this->followers->findByActorId($actorId) : [];
        if ($followers === []) {
            return Response::HTTP_ACCEPTED;
        }

        $signer = $followers[0]->getAccount();
        $key = $verify($signer);
        if ($key === Response::HTTP_TOO_MANY_REQUESTS) {
            return $key;
        }

        if (!$key instanceof RemoteKey) {
            if (!$this->mayFetch($request)) {
                return Response::HTTP_TOO_MANY_REQUESTS;
            }

            if (!$this->remoteKeys->isGone($actorId, $signer)) {
                return Response::HTTP_UNAUTHORIZED;
            }
        }

        foreach ($followers as $follower) {
            $this->em->remove($follower);
        }
        $this->em->flush();

        return Response::HTTP_ACCEPTED;
    }

    /**
     * @param array{keyId: string, algorithm: ?string, headers: list<string>, signature: string} $signature
     * @return RemoteKey|int the verified key, or the status to refuse with
     */
    private function verifiedKey(Request $request, array $signature, string $actorId, Account $signer): RemoteKey|int
    {
        $keyId = $signature['keyId'];
        if (!$this->remoteKeys->isCached($keyId) && !$this->mayFetch($request)) {
            return Response::HTTP_TOO_MANY_REQUESTS;
        }

        $key = $this->remoteKeys->find($keyId, $signer);
        if ($key instanceof RemoteKey && $this->verifies($request, $signature, $actorId, $key)) {
            return $key;
        }

        $keyAge = $key instanceof RemoteKey ? $this->clock->now()->getTimestamp() - $key->fetchedAt : 0;
        if ($keyAge <= self::KEY_REFRESH_AFTER_SECONDS) {
            return Response::HTTP_UNAUTHORIZED;
        }

        if (!$this->mayFetch($request)) {
            return Response::HTTP_TOO_MANY_REQUESTS;
        }

        $key = $this->remoteKeys->find($keyId, $signer, refresh: true);

        return $key instanceof RemoteKey && $this->verifies($request, $signature, $actorId, $key) ? $key : Response::HTTP_UNAUTHORIZED;
    }

    private function mayFetch(Request $request): bool
    {
        return $this->keyFetchLimiter
            ->create((string) $request->getClientIp())
            ->consume()
            ->isAccepted();
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
