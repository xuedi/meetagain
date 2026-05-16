<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\ExternalSource;
use Plugin\Filmclub\Entity\FilmclubSettings;
use Plugin\Filmclub\Repository\FilmclubSettingsRepository;
use Plugin\Filmclub\Service\FilmLookupResolver;
use Plugin\Filmclub\Service\FilmclubSettingsService;
use Plugin\Filmclub\Service\OmdbLookup;
use Plugin\Filmclub\Service\TmdbLookup;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

class FilmLookupResolverTest extends TestCase
{
    public function testResolveReturnsNullWhenNoSettingsRow(): void
    {
        // Arrange
        $repo = $this->createStub(FilmclubSettingsRepository::class);
        $repo->method('findGlobal')->willReturn(null);
        $resolver = $this->makeResolver(repo: $repo);

        // Act
        $result = $resolver->resolve();

        // Assert
        static::assertNull($result);
    }

    public function testResolveReturnsNullWhenAdapterIsNull(): void
    {
        // Arrange
        $settings = new FilmclubSettings();
        // adapter stays null
        $repo = $this->createStub(FilmclubSettingsRepository::class);
        $repo->method('findGlobal')->willReturn($settings);
        $resolver = $this->makeResolver(repo: $repo);

        // Act
        $result = $resolver->resolve();

        // Assert
        static::assertNull($result);
    }

    public function testResolveReturnsNullForManualAdapter(): void
    {
        // Arrange
        $settings = new FilmclubSettings();
        $settings->setAdapter(ExternalSource::Manual);
        $repo = $this->createStub(FilmclubSettingsRepository::class);
        $repo->method('findGlobal')->willReturn($settings);
        $resolver = $this->makeResolver(repo: $repo);

        // Act
        $result = $resolver->resolve();

        // Assert
        static::assertNull($result);
    }

    public function testResolveReturnsTmdbLookupWhenTmdbConfigured(): void
    {
        // Arrange
        $settings = new FilmclubSettings();
        $settings->setAdapter(ExternalSource::Tmdb);
        $settings->setEncryptedTmdbKey('encrypted-key');

        $repo = $this->createStub(FilmclubSettingsRepository::class);
        $repo->method('findGlobal')->willReturn($settings);

        $settingsService = $this->createStub(FilmclubSettingsService::class);
        $settingsService->method('getTmdbKey')->willReturn('cleartext-api-key');

        $resolver = $this->makeResolver(repo: $repo, settingsService: $settingsService);

        // Act
        $result = $resolver->resolve();

        // Assert
        static::assertInstanceOf(TmdbLookup::class, $result);
    }

    public function testResolveReturnsOmdbLookupWhenOmdbConfigured(): void
    {
        // Arrange
        $settings = new FilmclubSettings();
        $settings->setAdapter(ExternalSource::Omdb);
        $settings->setEncryptedOmdbKey('encrypted-key');

        $repo = $this->createStub(FilmclubSettingsRepository::class);
        $repo->method('findGlobal')->willReturn($settings);

        $settingsService = $this->createStub(FilmclubSettingsService::class);
        $settingsService->method('getOmdbKey')->willReturn('cleartext-api-key');

        $resolver = $this->makeResolver(repo: $repo, settingsService: $settingsService);

        // Act
        $result = $resolver->resolve();

        // Assert
        static::assertInstanceOf(OmdbLookup::class, $result);
    }

    public function testResolveReturnsNullWhenTmdbKeyIsMissing(): void
    {
        // Arrange
        $settings = new FilmclubSettings();
        $settings->setAdapter(ExternalSource::Tmdb);
        // No encrypted key set

        $repo = $this->createStub(FilmclubSettingsRepository::class);
        $repo->method('findGlobal')->willReturn($settings);

        $settingsService = $this->createStub(FilmclubSettingsService::class);
        $settingsService->method('getTmdbKey')->willReturn(null);

        $resolver = $this->makeResolver(repo: $repo, settingsService: $settingsService);

        // Act
        $result = $resolver->resolve();

        // Assert
        static::assertNull($result);
    }

    private function makeResolver(
        ?FilmclubSettingsRepository $repo = null,
        ?FilmclubSettingsService $settingsService = null,
    ): FilmLookupResolver {
        return new FilmLookupResolver(
            settingsRepository: $repo ?? $this->createStub(FilmclubSettingsRepository::class),
            settingsService: $settingsService ?? $this->createStub(FilmclubSettingsService::class),
            httpClient: new MockHttpClient(),
            logger: new NullLogger(),
        );
    }
}
