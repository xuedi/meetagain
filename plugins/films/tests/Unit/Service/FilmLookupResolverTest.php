<?php declare(strict_types=1);

namespace Plugin\Films\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Plugin\Films\Entity\ExternalSource;
use Plugin\Films\Entity\Settings;
use Plugin\Films\Repository\SettingsRepository;
use Plugin\Films\Service\FilmLookupResolver;
use Plugin\Films\Service\OmdbLookup;
use Plugin\Films\Service\SettingsService;
use Plugin\Films\Service\TmdbLookup;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class FilmLookupResolverTest extends TestCase
{
    /** @var list<string> */
    private array $requested = [];

    public function testWithoutSettingsOrKeysNoLookupIsOffered(): void
    {
        // Arrange
        $resolver = $this->resolver(null);

        // Act
        $lookup = $resolver->resolve();

        // Assert
        static::assertNull($lookup);
    }

    public function testWithoutSettingsTheTmdbEnvironmentKeyIsUsed(): void
    {
        // Arrange
        $resolver = $this->resolver(null, tmdbEnvironment: 'env-tmdb', omdbEnvironment: 'env-omdb');

        // Act
        $lookup = $resolver->resolve();
        $lookup?->searchByTitle('Dune', null, 'en');

        // Assert
        static::assertInstanceOf(TmdbLookup::class, $lookup);
        static::assertSame(['Authorization: Bearer env-tmdb'], $this->requested);
    }

    public function testWithoutSettingsTheOmdbEnvironmentKeyIsUsedWhenTmdbHasNone(): void
    {
        // Arrange
        $resolver = $this->resolver(null, omdbEnvironment: 'env-omdb');

        // Act
        $lookup = $resolver->resolve();
        $lookup?->searchByTitle('Dune', null, 'en');

        // Assert
        static::assertInstanceOf(OmdbLookup::class, $lookup);
        static::assertStringContainsString('apikey=env-omdb', $this->requested[0]);
    }

    public function testAStoredKeyWinsOverTheEnvironment(): void
    {
        // Arrange
        $settings = new Settings()->setAdapter(ExternalSource::Tmdb);
        $resolver = $this->resolver($settings, storedTmdb: 'stored-tmdb', tmdbEnvironment: 'env-tmdb');

        // Act
        $resolver->resolve()?->searchByTitle('Dune', null, 'en');

        // Assert
        static::assertSame(['Authorization: Bearer stored-tmdb'], $this->requested);
    }

    public function testTheStoredAdapterTakesItsEnvironmentKeyWhenNoneIsStored(): void
    {
        // Arrange
        $settings = new Settings()->setAdapter(ExternalSource::Omdb);
        $resolver = $this->resolver($settings, tmdbEnvironment: 'env-tmdb', omdbEnvironment: 'env-omdb');

        // Act
        $lookup = $resolver->resolve();

        // Assert
        static::assertInstanceOf(OmdbLookup::class, $lookup);
    }

    public function testTheManualAdapterOffersNoLookupDespiteEnvironmentKeys(): void
    {
        // Arrange
        $settings = new Settings()->setAdapter(ExternalSource::Manual);
        $resolver = $this->resolver($settings, tmdbEnvironment: 'env-tmdb');

        // Act
        $lookup = $resolver->resolve();

        // Assert
        static::assertNull($lookup);
    }

    private function resolver(
        ?Settings $settings,
        ?string $storedTmdb = null,
        ?string $tmdbEnvironment = null,
        ?string $omdbEnvironment = null,
    ): FilmLookupResolver {
        $repository = $this->createStub(SettingsRepository::class);
        $repository->method('findGlobal')->willReturn($settings);
        $settingsService = $this->createStub(SettingsService::class);
        $settingsService->method('getTmdbKey')->willReturn($storedTmdb);
        $settingsService->method('getOmdbKey')->willReturn(null);
        $httpClient = new MockHttpClient(function (string $method, string $url, array $options): MockResponse {
            $this->requested[] = $options['normalized_headers']['authorization'][0] ?? $url;

            return new MockResponse('{"results": []}');
        });

        return new FilmLookupResolver($repository, $settingsService, $httpClient, new NullLogger(), $tmdbEnvironment, $omdbEnvironment);
    }
}
