<?php declare(strict_types=1);

namespace Plugin\Filmclub\Service;

use Plugin\Filmclub\Entity\ExternalSource;
use Plugin\Filmclub\Entity\FilmclubGroupSettings;
use Plugin\Filmclub\Repository\FilmclubGroupSettingsRepository;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;

/**
 * Resolves the configured FilmMetadataLookupInterface for a given group.
 * Never injects GroupContextService - callers pass the group ID they already hold.
 */
readonly class FilmLookupResolver
{
    public function __construct(
        private FilmclubGroupSettingsRepository $settingsRepository,
        private FilmclubSettingsService $settingsService,
        private HttpClientInterface $httpClient,
        private LoggerInterface $logger,
    ) {}

    public function resolve(?int $groupId): ?FilmMetadataLookupInterface
    {
        if ($groupId === null) {
            return null;
        }

        $settings = $this->settingsRepository->findByGroupId($groupId);
        if ($settings === null || $settings->getAdapter() === null) {
            return null;
        }

        return match ($settings->getAdapter()) {
            ExternalSource::Tmdb => $this->createTmdb($settings),
            ExternalSource::Omdb => $this->createOmdb($settings),
            ExternalSource::Manual => null,
        };
    }

    private function createTmdb(FilmclubGroupSettings $settings): ?FilmMetadataLookupInterface
    {
        $key = $this->settingsService->getTmdbKey($settings);
        if ($key === null) {
            return null;
        }

        return new TmdbLookup($this->httpClient, $this->logger, $key);
    }

    private function createOmdb(FilmclubGroupSettings $settings): ?FilmMetadataLookupInterface
    {
        $key = $this->settingsService->getOmdbKey($settings);
        if ($key === null) {
            return null;
        }

        return new OmdbLookup($this->httpClient, $this->logger, $key);
    }
}
