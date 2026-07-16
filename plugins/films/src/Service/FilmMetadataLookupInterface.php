<?php declare(strict_types=1);

namespace Plugin\Films\Service;

use Plugin\Films\Entity\ExternalSource;

/**
 * Retrieves film metadata from an external source.
 * Implementations are instantiated by FilmLookupResolver with the configured API key.
 */
interface FilmMetadataLookupInterface
{
    /**
     * Search for films matching the given query.
     *
     * @return FilmMetadata[]
     */
    public function searchByTitle(string $query, ?int $year, string $locale): array;

    public function fetchById(string $externalId, string $locale): ?FilmMetadata;

    public function getSource(): ExternalSource;
}
