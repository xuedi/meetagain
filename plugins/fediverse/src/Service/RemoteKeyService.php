<?php declare(strict_types=1);

namespace Plugin\Fediverse\Service;

use Plugin\Fediverse\Entity\Account;
use Plugin\Fediverse\Entity\Follower;
use Plugin\Fediverse\Security\HttpSignature;
use Plugin\Fediverse\ValueObject\RemoteKey;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\CacheInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class RemoteKeyService
{
    public const int MAX_DOCUMENT_BYTES = 262144;
    public const int MISS_TTL_SECONDS = 300;

    private const string ACCEPT = 'application/activity+json, application/ld+json; profile="https://www.w3.org/ns/activitystreams"';

    public function __construct(
        #[Autowire(service: 'remote.client')]
        private HttpClientInterface $client,
        #[Autowire(service: 'cache.fediverse_remote_key')]
        private CacheInterface $cache,
        private HttpSignature $httpSignature,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    public function hostOf(string $url): ?string
    {
        $host = parse_url($url, PHP_URL_HOST);
        if (parse_url($url, PHP_URL_SCHEME) !== 'https' || !is_string($host)) {
            return null;
        }

        return strtolower($host);
    }

    public function find(string $keyId, ?Account $signer, bool $refresh = false): ?RemoteKey
    {
        $cacheKey = 'key.' . hash('xxh128', $keyId);
        if ($refresh) {
            $this->cache->delete($cacheKey);
        }

        return $this->cache->get($cacheKey, function (ItemInterface $item) use ($keyId, $signer): ?RemoteKey {
            $key = $this->fetch($keyId, $signer);
            if (!$key instanceof RemoteKey) {
                $item->expiresAfter(self::MISS_TTL_SECONDS);
            }

            return $key;
        });
    }

    public function isGone(string $actorId, ?Account $signer): bool
    {
        return $this->document($actorId, $signer) === 410;
    }

    private function fetch(string $keyId, ?Account $signer): ?RemoteKey
    {
        $host = $this->hostOf($keyId);
        $actor = $host === null ? 0 : $this->document($keyId, $signer);
        if ($host === null || !is_array($actor) || !$this->isOn($host, $actor['id'] ?? null)) {
            return null;
        }

        $pem = $this->pemIn($actor, $keyId);
        $owner = $actor['owner'] ?? null;
        if ($pem === null && $actor['id'] === $keyId && is_string($owner) && $this->isOn($host, $owner)) {
            $keyPem = $actor['publicKeyPem'] ?? null;
            $actor = $this->document($owner, $signer);
            $pem = is_array($actor) && ($actor['id'] ?? null) === $owner ? $this->pemIn($actor, $keyId) : null;
            $pem = $pem === $keyPem ? $pem : null;
        }

        if ($pem === null || !is_array($actor) || !is_string($actor['id'] ?? null) || !$this->isOn($host, $actor['inbox'] ?? null)) {
            return null;
        }

        $sharedInbox = $actor['endpoints']['sharedInbox'] ?? null;

        return new RemoteKey(
            keyId: $keyId,
            ownerId: $actor['id'],
            publicKeyPem: $pem,
            inbox: (string) $actor['inbox'],
            sharedInbox: $this->isOn($host, $sharedInbox) ? (string) $sharedInbox : null,
            fetchedAt: $this->clock->now()->getTimestamp(),
        );
    }

    /** @param array<mixed> $document */
    private function pemIn(array $document, string $keyId): ?string
    {
        $keys = $document['publicKey'] ?? [];
        $keys = is_array($keys) && array_is_list($keys) ? $keys : [$keys];
        foreach ($keys as $key) {
            if (is_array($key) && ($key['id'] ?? null) === $keyId && is_string($key['publicKeyPem'] ?? null)) {
                $owner = $key['owner'] ?? $document['id'] ?? null;

                return $owner === ($document['id'] ?? null) ? $key['publicKeyPem'] : null;
            }
        }

        return null;
    }

    private function isOn(string $host, mixed $url): bool
    {
        return is_string($url) && strlen($url) <= Follower::MAX_URL_LENGTH && $this->hostOf($url) === $host;
    }

    /** @return array<mixed>|int the decoded document, or the status code when there is none (0 when nothing answered) */
    private function document(string $url, ?Account $signer): array|int
    {
        $url = explode('#', $url, 2)[0];
        if ($this->hostOf($url) === null) {
            return 0;
        }

        $headers = ['accept' => self::ACCEPT];
        if ($signer instanceof Account) {
            $headers += $this->httpSignature->sign($signer, 'GET', $url, null);
        }

        try {
            $response = $this->client->request('GET', $url, ['headers' => $headers]);
            $status = $response->getStatusCode();
            if ($status !== 200) {
                $response->cancel();

                return $status;
            }

            $body = '';
            foreach ($this->client->stream($response) as $chunk) {
                $body .= $chunk->getContent();
                if (strlen($body) > self::MAX_DOCUMENT_BYTES) {
                    $response->cancel();

                    return 0;
                }
            }
        } catch (ExceptionInterface $e) {
            $this->logger->info('Fediverse: fetching a remote key failed.', ['url' => $url, 'exception' => $e]);

            return 0;
        }

        $document = json_decode($body, true);

        return is_array($document) ? $document : 0;
    }
}
