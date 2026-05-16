<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use Plugin\Filmclub\Entity\ExternalSource;
use Plugin\Filmclub\Entity\FilmclubSettings;
use Plugin\Filmclub\Repository\FilmclubSettingsRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class FilmLookupResolver
{
    public function __construct(
        private FilmclubSettingsRepository $settingsRepository,
        private FilmclubSettingsService $settingsService,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {}

    public function resolve(): ?FilmMetadataLookupInterface
    {
        $settings = $this->settingsRepository->findGlobal();
        if ($settings === null || $settings->getAdapter() === null) {
            return null;
        }

        return $this->resolveFromSettings($settings);
    }

    private function resolveFromSettings(FilmclubSettings $settings): ?FilmMetadataLookupInterface
    {
        return match ($settings->getAdapter()) {
            ExternalSource::Tmdb => $this->createTmdb($settings),
            ExternalSource::Omdb => $this->createOmdb($settings),
            default => null,
        };
    }

    private function createTmdb(FilmclubSettings $settings): ?FilmMetadataLookupInterface
    {
        $key = $this->settingsService->getTmdbKey($settings);
        if ($key === null) {
            return null;
        }

        return new TmdbLookup($this->httpClient, $this->logger, $key);
    }

    private function createOmdb(FilmclubSettings $settings): ?FilmMetadataLookupInterface
    {
        $key = $this->settingsService->getOmdbKey($settings);
        if ($key === null) {
            return null;
        }

        return new OmdbLookup($this->httpClient, $this->logger, $key);
    }
}
