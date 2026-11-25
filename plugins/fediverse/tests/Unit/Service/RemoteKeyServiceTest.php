<?php declare(strict_types=1);

namespace Plugin\Fediverse\Tests\Unit\Service;

use DateTimeImmutable;
use PHPUnit\Framework\TestCase;
use Plugin\Fediverse\Security\HttpSignature;
use Plugin\Fediverse\Service\RemoteKeyService;
use Plugin\Fediverse\ValueObject\RemoteKey;
use Psr\Log\NullLogger;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class RemoteKeyServiceTest extends TestCase
{
    private const string KEY_ID = 'https://social.example/users/alice#main-key';
    private const string ACTOR_ID = 'https://social.example/users/alice';

    /** @var list<string> */
    private array $fetched = [];

    /** @var list<array<string, mixed>> */
    private array $options = [];

    public function testAMastodonStyleActorYieldsItsKeyAndInboxes(): void
    {
        // Arrange
        $service = $this->service([self::ACTOR_ID => $this->actor()]);

        // Act
        $key = $service->find(self::KEY_ID, null);

        // Assert
        static::assertInstanceOf(RemoteKey::class, $key);
        static::assertSame(self::ACTOR_ID, $key->ownerId);
        static::assertSame('PEM', $key->publicKeyPem);
        static::assertSame(self::ACTOR_ID . '/inbox', $key->inbox);
        static::assertSame('https://social.example/inbox', $key->sharedInbox);
    }

    public function testAKeyIsFetchedOnceAndThenServedFromTheCache(): void
    {
        // Arrange
        $service = $this->service([self::ACTOR_ID => $this->actor()]);

        // Act
        $service->find(self::KEY_ID, null);
        $service->find(self::KEY_ID, null);

        // Assert
        static::assertSame([self::ACTOR_ID], $this->fetched);
    }

    public function testAKeyDocumentOfItsOwnIsConfirmedByItsOwner(): void
    {
        // Arrange
        $keyId = 'https://social.example/users/alice/main-key';
        $service = $this->service([
            $keyId => ['id' => $keyId, 'owner' => self::ACTOR_ID, 'publicKeyPem' => 'PEM'],
            self::ACTOR_ID => $this->actor(['publicKey' => ['id' => $keyId, 'owner' => self::ACTOR_ID, 'publicKeyPem' => 'PEM']]),
        ]);

        // Act
        $key = $service->find($keyId, null);

        // Assert
        static::assertInstanceOf(RemoteKey::class, $key);
        static::assertSame(self::ACTOR_ID, $key->ownerId);
    }

    public function testADocumentFromAnotherHostIsNoKey(): void
    {
        // Arrange
        $service = $this->service([self::ACTOR_ID => $this->actor(['id' => 'https://elsewhere.example/users/alice'])]);

        // Act + Assert
        static::assertNull($service->find(self::KEY_ID, null));
    }

    public function testAnInboxOnAnotherHostIsRefused(): void
    {
        // Arrange
        $service = $this->service([self::ACTOR_ID => $this->actor(['inbox' => 'https://victim.example/hook'])]);

        // Act + Assert
        static::assertNull($service->find(self::KEY_ID, null));
    }

    public function testAKeyOwnedBySomeoneElseIsRefused(): void
    {
        // Arrange
        $actor = $this->actor(['publicKey' => ['id' => self::KEY_ID, 'owner' => 'https://social.example/users/mallory', 'publicKeyPem' => 'PEM']]);
        $service = $this->service([self::ACTOR_ID => $actor]);

        // Act + Assert
        static::assertNull($service->find(self::KEY_ID, null));
    }

    public function testAnOversizedDocumentIsRefused(): void
    {
        // Arrange
        $service = $this->service([self::ACTOR_ID => $this->actor(['summary' => str_repeat('x', RemoteKeyService::MAX_DOCUMENT_BYTES)])]);

        // Act + Assert
        static::assertNull($service->find(self::KEY_ID, null));
    }

    public function testAPlainHttpKeyIdIsNeverFetched(): void
    {
        // Arrange
        $service = $this->service([]);

        // Act
        $key = $service->find('http://social.example/users/alice#main-key', null);

        // Assert
        static::assertNull($key);
        static::assertSame([], $this->fetched);
    }

    public function testAnActorAnsweringGoneIsGone(): void
    {
        // Arrange
        $service = $this->service([self::ACTOR_ID => 410]);

        // Act + Assert
        static::assertTrue($service->isGone(self::ACTOR_ID, null));
    }

    public function testAMissIsNotFetchedAgainUntilARefreshIsForced(): void
    {
        // Arrange
        $service = $this->service([self::ACTOR_ID => 404]);

        // Act
        $first = $service->find(self::KEY_ID, null);
        $second = $service->find(self::KEY_ID, null);
        $service->find(self::KEY_ID, null, refresh: true);

        // Assert
        static::assertNull($first);
        static::assertNull($second);
        static::assertSame([self::ACTOR_ID, self::ACTOR_ID], $this->fetched, 'once for the miss, once for the refresh');
    }

    public function testAPublicKeyGivenAsAListIsRead(): void
    {
        // Arrange
        $service = $this->service([
            self::ACTOR_ID => $this->actor([
                'publicKey' => [
                    ['id' => self::ACTOR_ID . '#other-key', 'owner' => self::ACTOR_ID, 'publicKeyPem' => 'OTHER'],
                    ['id' => self::KEY_ID, 'owner' => self::ACTOR_ID, 'publicKeyPem' => 'PEM'],
                ],
            ]),
        ]);

        // Act
        $key = $service->find(self::KEY_ID, null);

        // Assert
        static::assertSame('PEM', $key?->publicKeyPem);
    }

    public function testAServerErrorGivesNoKey(): void
    {
        // Arrange
        $service = $this->service([self::ACTOR_ID => 500]);

        // Act + Assert
        static::assertNull($service->find(self::KEY_ID, null));
    }

    public function testABodyThatIsNoJsonGivesNoKey(): void
    {
        // Arrange
        $service = $this->service([self::ACTOR_ID => 'not json']);

        // Act + Assert
        static::assertNull($service->find(self::KEY_ID, null));
    }

    public function testAKeyDocumentWhoseOwnerPublishesAnotherPemIsRefused(): void
    {
        // Arrange
        $keyId = 'https://social.example/users/alice/main-key';
        $service = $this->service([
            $keyId => ['id' => $keyId, 'owner' => self::ACTOR_ID, 'publicKeyPem' => 'PEM'],
            self::ACTOR_ID => $this->actor(['publicKey' => ['id' => $keyId, 'owner' => self::ACTOR_ID, 'publicKeyPem' => 'OTHER PEM']]),
        ]);

        // Act + Assert
        static::assertNull($service->find($keyId, null));
    }

    public function testTheMockReportsTheUrlARedirectEndedOn(): void
    {
        // Arrange
        $client = new MockHttpClient(new MockResponse('{}', ['http_code' => 200, 'url' => 'https://evil.example/users/alice', 'redirect_count' => 1]));

        // Act
        $response = $client->request('GET', self::ACTOR_ID);

        // Assert
        static::assertSame(200, $response->getStatusCode());
        static::assertSame('https://evil.example/users/alice', $response->getInfo('url'), 'the tests below rely on it');
    }

    public function testARedirectToAnotherHostCannotVouchForAnActorOfTheFirst(): void
    {
        // Arrange
        $service = $this->service([self::ACTOR_ID => $this->servedFrom('https://evil.example/users/alice', $this->actor())]);

        // Act + Assert
        static::assertNull($service->find(self::KEY_ID, null));
    }

    public function testARedirectWithinTheHostToTheCanonicalIdIsAccepted(): void
    {
        // Arrange
        $canonical = 'https://social.example/@alice';
        $service = $this->service([
            self::ACTOR_ID => $this->servedFrom($canonical, $this->actor([
                'id' => $canonical,
                'publicKey' => ['id' => self::KEY_ID, 'owner' => $canonical, 'publicKeyPem' => 'PEM'],
            ])),
        ]);

        // Act
        $key = $service->find(self::KEY_ID, null);

        // Assert
        static::assertSame($canonical, $key?->ownerId);
    }

    public function testAnOwnerDocumentServedFromElsewhereIsRefused(): void
    {
        // Arrange
        $keyId = 'https://social.example/users/alice/main-key';
        $service = $this->service([
            $keyId => ['id' => $keyId, 'owner' => self::ACTOR_ID, 'publicKeyPem' => 'PEM'],
            self::ACTOR_ID => $this->servedFrom('https://social.example/redirected', $this->actor([
                'publicKey' => ['id' => $keyId, 'owner' => self::ACTOR_ID, 'publicKeyPem' => 'PEM'],
            ])),
        ]);

        // Act + Assert
        static::assertNull($service->find($keyId, null));
    }

    public function testGoneIsNeverAskedThroughARedirect(): void
    {
        // Arrange
        $service = $this->service([self::ACTOR_ID => 301]);

        // Act
        $isGone = $service->isGone(self::ACTOR_ID, null);

        // Assert
        static::assertFalse($isGone);
        static::assertSame(0, $this->options[0]['max_redirects']);
    }

    public function testAFetchCarriesItsOwnShortBudget(): void
    {
        // Arrange
        $service = $this->service([self::ACTOR_ID => $this->actor()]);

        // Act
        $service->find(self::KEY_ID, null);

        // Assert
        static::assertEqualsWithDelta(RemoteKeyService::FETCH_TIMEOUT_SECONDS, $this->options[0]['timeout'], 0.001);
        static::assertEqualsWithDelta(RemoteKeyService::FETCH_MAX_DURATION_SECONDS, $this->options[0]['max_duration'], 0.001);
        static::assertNotSame(0, $this->options[0]['max_redirects'] ?? null, 'a key fetch may follow a redirect within the host');
    }

    /** @param array<string, mixed> $document */
    private function servedFrom(string $url, array $document): MockResponse
    {
        return new MockResponse(json_encode($document, JSON_THROW_ON_ERROR), ['http_code' => 200, 'url' => $url, 'redirect_count' => 1]);
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function actor(array $overrides = []): array
    {
        return [
            'id' => self::ACTOR_ID,
            'type' => 'Person',
            'inbox' => self::ACTOR_ID . '/inbox',
            'endpoints' => ['sharedInbox' => 'https://social.example/inbox'],
            'publicKey' => ['id' => self::KEY_ID, 'owner' => self::ACTOR_ID, 'publicKeyPem' => 'PEM'],
            ...$overrides,
        ];
    }

    /** @param array<string, array<string, mixed>|int|string|MockResponse> $documents */
    private function service(array $documents): RemoteKeyService
    {
        $client = new MockHttpClient(function (string $method, string $url, array $options) use ($documents): MockResponse {
            $this->fetched[] = $url;
            $this->options[] = $options;
            $document = $documents[$url] ?? 404;
            if ($document instanceof MockResponse) {
                return $document;
            }

            if (is_int($document)) {
                return new MockResponse('', ['http_code' => $document]);
            }

            return new MockResponse(is_string($document) ? $document : json_encode($document, JSON_THROW_ON_ERROR), ['http_code' => 200]);
        });

        return new RemoteKeyService(
            $client,
            new ArrayAdapter(),
            $this->createStub(HttpSignature::class),
            new MockClock(new DateTimeImmutable('2026-11-25T10:00:00+00:00')),
            new NullLogger(),
        );
    }
}
