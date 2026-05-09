<?php declare(strict_types=1);

namespace App\Service\Security\Incident;

/**
 * One source's contribution to a (potentially new) Incident keyed by IP.
 * The merger uses these to upsert; counter / sample fields are added to the
 * existing Incident or used to build a fresh row.
 */
final readonly class IncidentSourceContribution
{
    public const string KEY_PROBING = 'url_probing';
    public const string KEY_ACCESS_DENIED = 'access_denied';
    public const string KEY_RATE_LIMIT = 'rate_limit';

    /**
     * @param list<string> $samplePaths
     * @param array<string, int> $userAgentCounts ua => count
     */
    public function __construct(
        public string $ip,
        public string $sourceKey,
        public int $hits,
        public \DateTimeImmutable $startedAt,
        public \DateTimeImmutable $endedAt,
        public int $distinctPaths,
        public int $distinctUserAgents,
        public array $samplePaths,
        public array $userAgentCounts,
    ) {}
}
