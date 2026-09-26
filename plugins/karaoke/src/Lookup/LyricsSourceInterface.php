<?php declare(strict_types=1);

namespace Plugin\Karaoke\Lookup;

use Plugin\Karaoke\ValueObject\Config;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Contracts\HttpClient\Exception\ExceptionInterface;

/**
 * One lyrics service in the lookup chain. LyricsLookup asks every enabled source, in priority order,
 * and owns caching, ranking and failure handling, so a source only speaks its service's protocol.
 */
#[AutoconfigureTag]
interface LyricsSourceInterface
{
    /** Stable key, stored in the pick parameter as "key:externalId". */
    public function getKey(): string;

    public function isEnabled(Config $config): bool;

    /** Order in the chain (descending). */
    public function getPriority(): int;

    /**
     * @throws ExceptionInterface when the service is unreachable or answers with an error
     *
     * @return list<Candidate>
     */
    public function search(string $title, ?string $artist): array;

    /**
     * @throws ExceptionInterface when the service is unreachable or answers with an error
     */
    public function fetch(string $externalId): ?Candidate;
}
