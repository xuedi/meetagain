<?php declare(strict_types=1);

namespace Plugin\Films\Service;

use Plugin\Films\Entity\ExternalSource;
use Plugin\Films\Entity\Settings;
use Plugin\Films\Repository\SettingsRepository;
use Psr\Log\LoggerInterface;
use SensitiveParameter;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class FilmLookupResolver
{
    public function __construct(
        private SettingsRepository $settingsRepository,
        private SettingsService $settingsService,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
        #[Autowire('%env(default::TMDB_API_KEY)%')]
        #[SensitiveParameter]
        private ?string $tmdbApiKey = null,
        #[Autowire('%env(default::OMDB_API_KEY)%')]
        #[SensitiveParameter]
        private ?string $omdbApiKey = null,
    ) {}

    public function resolve(): ?FilmMetadataLookupInterface
    {
        $settings = $this->settingsRepository->findGlobal();

        return match ($settings?->getAdapter() ?? $this->environmentAdapter()) {
            ExternalSource::Tmdb => $this->createTmdb($settings),
            ExternalSource::Omdb => $this->createOmdb($settings),
            default => null,
        };
    }

    private function environmentAdapter(): ?ExternalSource
    {
        if ($this->tmdbApiKey !== null && $this->tmdbApiKey !== '') {
            return ExternalSource::Tmdb;
        }
        if ($this->omdbApiKey !== null && $this->omdbApiKey !== '') {
            return ExternalSource::Omdb;
        }

        return null;
    }

    private function createTmdb(?Settings $settings): ?FilmMetadataLookupInterface
    {
        $key = ($settings === null ? null : $this->settingsService->getTmdbKey($settings)) ?? ($this->tmdbApiKey ?: null);
        if ($key === null) {
            return null;
        }

        return new TmdbLookup($this->httpClient, $this->logger, $key);
    }

    private function createOmdb(?Settings $settings): ?FilmMetadataLookupInterface
    {
        $key = ($settings === null ? null : $this->settingsService->getOmdbKey($settings)) ?? ($this->omdbApiKey ?: null);
        if ($key === null) {
            return null;
        }

        return new OmdbLookup($this->httpClient, $this->logger, $key);
    }
}
