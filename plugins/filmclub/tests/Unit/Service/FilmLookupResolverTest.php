<?php declare(strict_types=1);

namespace Plugin\Filmclub\Tests\Unit\Service;

use PHPUnit\Framework\TestCase;
use Plugin\Filmclub\Entity\ExternalSource;
use Plugin\Filmclub\Entity\FilmclubGroupSettings;
use Plugin\Filmclub\Repository\FilmclubGroupSettingsRepository;
use Plugin\Filmclub\Service\FilmLookupResolver;
use Plugin\Filmclub\Service\FilmclubSettingsService;
use Plugin\Filmclub\Service\OmdbLookup;
use Plugin\Filmclub\Service\TmdbLookup;
use Psr\Log\NullLogger;
use Symfony\Component\HttpClient\MockHttpClient;

class FilmLookupResolverTest extends TestCase
{
    public function testResolveReturnsNullWhenGroupIdIsNull(): void
    {
        // Arrange
        $resolver = $this->makeResolver();

        // Act
        $result = $resolver->resolve(null);

        // Assert
        static::assertNull($result);
    }

    public function testResolveReturnsNullWhenNoSettingsRow(): void
    {
        // Arrange
        $repo = $this->createStub(FilmclubGroupSettingsRepository::class);
        $repo->method('findByGroupId')->willReturn(null);
        $resolver = $this->makeResolver(repo: $repo);

        // Act
        $result = $resolver->resolve(1);

        // Assert
        static::assertNull($result);
    }

    public function testResolveReturnsNullWhenAdapterIsNull(): void
    {
        // Arrange
        $settings = new FilmclubGroupSettings();
        $settings->setGroupId(1);
        // adapter stays null
        $repo = $this->createStub(FilmclubGroupSettingsRepository::class);
        $repo->method('findByGroupId')->willReturn($settings);
        $resolver = $this->makeResolver(repo: $repo);

        // Act
        $result = $resolver->resolve(1);

        // Assert
        static::assertNull($result);
    }

    public function testResolveReturnsNullForManualAdapter(): void
    {
        // Arrange
        $settings = new FilmclubGroupSettings();
        $settings->setGroupId(1);
        $settings->setAdapter(ExternalSource::Manual);
        $repo = $this->createStub(FilmclubGroupSettingsRepository::class);
        $repo->method('findByGroupId')->willReturn($settings);
        $resolver = $this->makeResolver(repo: $repo);

        // Act
        $result = $resolver->resolve(1);

        // Assert
        static::assertNull($result);
    }

    public function testResolveReturnsTmdbLookupWhenTmdbConfigured(): void
    {
        // Arrange
        $settings = new FilmclubGroupSettings();
        $settings->setGroupId(1);
        $settings->setAdapter(ExternalSource::Tmdb);
        $settings->setEncryptedTmdbKey('encrypted-key');

        $repo = $this->createStub(FilmclubGroupSettingsRepository::class);
        $repo->method('findByGroupId')->willReturn($settings);

        $settingsService = $this->createStub(FilmclubSettingsService::class);
        $settingsService->method('getTmdbKey')->willReturn('cleartext-api-key');

        $resolver = $this->makeResolver(repo: $repo, settingsService: $settingsService);

        // Act
        $result = $resolver->resolve(1);

        // Assert
        static::assertInstanceOf(TmdbLookup::class, $result);
    }

    public function testResolveReturnsOmdbLookupWhenOmdbConfigured(): void
    {
        // Arrange
        $settings = new FilmclubGroupSettings();
        $settings->setGroupId(1);
        $settings->setAdapter(ExternalSource::Omdb);
        $settings->setEncryptedOmdbKey('encrypted-key');

        $repo = $this->createStub(FilmclubGroupSettingsRepository::class);
        $repo->method('findByGroupId')->willReturn($settings);

        $settingsService = $this->createStub(FilmclubSettingsService::class);
        $settingsService->method('getOmdbKey')->willReturn('cleartext-api-key');

        $resolver = $this->makeResolver(repo: $repo, settingsService: $settingsService);

        // Act
        $result = $resolver->resolve(1);

        // Assert
        static::assertInstanceOf(OmdbLookup::class, $result);
    }

    public function testResolveReturnsNullWhenTmdbKeyIsMissing(): void
    {
        // Arrange
        $settings = new FilmclubGroupSettings();
        $settings->setGroupId(1);
        $settings->setAdapter(ExternalSource::Tmdb);
        // No encrypted key set

        $repo = $this->createStub(FilmclubGroupSettingsRepository::class);
        $repo->method('findByGroupId')->willReturn($settings);

        $settingsService = $this->createStub(FilmclubSettingsService::class);
        $settingsService->method('getTmdbKey')->willReturn(null);

        $resolver = $this->makeResolver(repo: $repo, settingsService: $settingsService);

        // Act
        $result = $resolver->resolve(1);

        // Assert
        static::assertNull($result);
    }

    private function makeResolver(
        ?FilmclubGroupSettingsRepository $repo = null,
        ?FilmclubSettingsService $settingsService = null,
    ): FilmLookupResolver {
        return new FilmLookupResolver(
            settingsRepository: $repo ?? $this->createStub(FilmclubGroupSettingsRepository::class),
            settingsService: $settingsService ?? $this->createStub(FilmclubSettingsService::class),
            httpClient: new MockHttpClient(),
            logger: new NullLogger(),
        );
    }
}
