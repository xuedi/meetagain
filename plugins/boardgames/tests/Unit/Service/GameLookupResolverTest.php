<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Unit\Service;

use App\Service\Security\SecretBox;
use PHPUnit\Framework\TestCase;
use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\Service\BggLookup;
use Plugin\Boardgames\Service\ConfigService;
use Plugin\Boardgames\Service\GameLookupResolver;
use Plugin\Boardgames\Service\WikidataLookup;
use Plugin\Boardgames\ValueObject\Config;
use Psr\Log\NullLogger;
use SensitiveParameter;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class GameLookupResolverTest extends TestCase
{
    private SecretBox $secretBox;

    /** @var list<string> */
    private array $authorizations = [];

    protected function setUp(): void
    {
        $this->secretBox = new SecretBox(base64_encode(random_bytes(SODIUM_CRYPTO_SECRETBOX_KEYBYTES)));
    }

    public function testWithoutAdapterOrTokenNoLookupIsOffered(): void
    {
        // Arrange
        $resolver = $this->resolver(new Config(), null);

        // Act
        $lookup = $resolver->resolve();

        // Assert
        static::assertNull($lookup);
    }

    public function testWithoutAdapterTheEnvironmentTokenSelectsBoardGameGeek(): void
    {
        // Arrange
        $resolver = $this->resolver(new Config(), 'env-token');

        // Act
        $lookup = $resolver->resolve();
        $lookup?->searchByName('catan');

        // Assert
        static::assertInstanceOf(BggLookup::class, $lookup);
        static::assertSame(['Authorization: Bearer env-token'], $this->authorizations);
    }

    public function testAStoredTokenWinsOverTheEnvironment(): void
    {
        // Arrange
        $config = new Config()->setAdapter(ExternalSource::Bgg);
        $config->setEncryptedBggToken($this->secretBox->encrypt('stored-token'));
        $resolver = $this->resolver($config, 'env-token');

        // Act
        $resolver->resolve()?->searchByName('catan');

        // Assert
        static::assertSame(['Authorization: Bearer stored-token'], $this->authorizations);
    }

    public function testAChosenWikidataAdapterIgnoresTheEnvironmentToken(): void
    {
        // Arrange
        $resolver = $this->resolver(new Config()->setAdapter(ExternalSource::Wikidata), 'env-token');

        // Act
        $lookup = $resolver->resolve();

        // Assert
        static::assertInstanceOf(WikidataLookup::class, $lookup);
    }

    public function testBoardGameGeekWithoutAnyTokenOffersNoLookup(): void
    {
        // Arrange
        $resolver = $this->resolver(new Config()->setAdapter(ExternalSource::Bgg), null);

        // Act
        $lookup = $resolver->resolve();

        // Assert
        static::assertNull($lookup);
    }

    private function resolver(Config $config, #[SensitiveParameter] ?string $environmentToken): GameLookupResolver
    {
        $configService = $this->createStub(ConfigService::class);
        $configService->method('getConfig')->willReturn($config);
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->authorizations[] = $options['normalized_headers']['authorization'][0] ?? '';

            return new MockResponse('<items></items>');
        });

        return new GameLookupResolver($configService, $this->secretBox, $httpClient, new NullLogger(), $environmentToken);
    }
}
