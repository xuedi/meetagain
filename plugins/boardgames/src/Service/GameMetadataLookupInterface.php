<?php declare(strict_types=1);

namespace Plugin\Boardgames\Service;

use Plugin\Boardgames\Enum\ExternalSource;
use Plugin\Boardgames\Enum\LookupFailure;

/**
 * Retrieves board game metadata from an external source. Implementations are instantiated by
 * GameLookupResolver with the resolved configuration and never throw into the request: a failed
 * call returns an empty result and records why, so the caller can tell the steward what went wrong.
 */
interface GameMetadataLookupInterface
{
    /** @return list<GameMetadata> */
    public function searchByName(string $query): array;

    public function fetchById(string $externalId): ?GameMetadata;

    public function getSource(): ExternalSource;

    /** Why the last call returned nothing, or null when it succeeded. */
    public function getLastFailure(): ?LookupFailure;
}
