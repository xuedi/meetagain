<?php declare(strict_types=1);

namespace Plugin\Films\Service;

use Plugin\Films\Entity\ExternalSource;
use Plugin\Films\Entity\FilmsSettings;
use Plugin\Films\Repository\FilmsSettingsRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

readonly class FilmLookupResolver
{
    public function __construct(
        private FilmsSettingsRepository $settingsRepository,
        private FilmsSettingsService $settingsService,
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

    private function resolveFromSettings(FilmsSettings $settings): ?FilmMetadataLookupInterface
    {
        return match ($settings->getAdapter()) {
            ExternalSource::Tmdb => $this->createTmdb($settings),
            ExternalSource::Omdb => $this->createOmdb($settings),
            default => null,
        };
    }

    private function createTmdb(FilmsSettings $settings): ?FilmMetadataLookupInterface
    {
        $key = $this->settingsService->getTmdbKey($settings);
        if ($key === null) {
            return null;
        }

        return new TmdbLookup($this->httpClient, $this->logger, $key);
    }

    private function createOmdb(FilmsSettings $settings): ?FilmMetadataLookupInterface
    {
        $key = $this->settingsService->getOmdbKey($settings);
        if ($key === null) {
            return null;
        }

        return new OmdbLookup($this->httpClient, $this->logger, $key);
    }
}
