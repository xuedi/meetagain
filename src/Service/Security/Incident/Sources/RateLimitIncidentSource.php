<?php declare(strict_types=1);

namespace App\Service\Security\Incident\Sources;

use App\Entity\RateLimitLog;
use App\Repository\RateLimitLogRepository;
use App\Service\AppStateService;
use App\Service\Security\Incident\IncidentMerger;
use App\Service\Security\Incident\IncidentSourceContribution;
use App\Service\Security\Incident\IncidentSourceInterface;
use App\Service\Security\Incident\IncidentSourceStats;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Symfony\Component\Clock\ClockInterface;

/**
 * Reads every rate-limit hit (login throttling, comment throttle, registration
 * throttle, etc.). The `limiter` field on each row preserves which limiter
 * tripped, in case future scoring wants to weight credential attacks
 * (login_throttling) higher than other limiters.
 */
final readonly class RateLimitIncidentSource implements IncidentSourceInterface
{
    public const string KEY = IncidentSourceContribution::KEY_RATE_LIMIT;
    public const string KEY_LAST_PROCESSED_ID = 'security.incident.rate_limit.last_processed_log_id';
    public const int SETTLE_MINUTES = 5;
    public const int MIN_HITS_PER_INCIDENT = 1;
    public const int BATCH_SIZE = 5000;
    public const int MAX_SAMPLE_URLS = 10;

    public function __construct(
        private EntityManagerInterface $em,
        private RateLimitLogRepository $repo,
        private IncidentMerger $merger,
        private AppStateService $appState,
        private ClockInterface $clock,
    ) {}

    #[Override]
    public function getKey(): string
    {
        return self::KEY;
    }

    #[Override]
    public function ingest(): IncidentSourceStats
    {
        $now = $this->clock->now();
        $cutoff = $now->modify('-' . self::SETTLE_MINUTES . ' minutes');
        $lastId = (int) ($this->appState->get(self::KEY_LAST_PROCESSED_ID) ?? '0');

        $rows = $this->repo->findRowsAfterIdUpTo($lastId, $cutoff, self::BATCH_SIZE);
        if ($rows === []) {
            return IncidentSourceStats::empty(self::KEY, $lastId);
        }

        $batchMaxId = 0;
        $rowsByIp = [];
        foreach ($rows as $row) {
            $id = (int) $row->getId();
            if ($id > $batchMaxId) {
                $batchMaxId = $id;
            }
            $rowsByIp[$row->getIp()][] = $row;
        }

        $incidentsTouched = 0;
        foreach ($rowsByIp as $ip => $ipRows) {
            $this->merger->merge($this->buildContribution($ip, $ipRows));
            $incidentsTouched++;
        }

        $this->em->flush();

        $hasMore = count($rows) >= self::BATCH_SIZE;
        if ($batchMaxId > $lastId) {
            $this->appState->set(self::KEY_LAST_PROCESSED_ID, (string) $batchMaxId);
        }

        return new IncidentSourceStats(
            sourceKey: self::KEY,
            considered: count($rows),
            ipsTouched: count($rowsByIp),
            incidentsTouched: $incidentsTouched,
            lastProcessedId: max($lastId, $batchMaxId),
            hasMore: $hasMore,
        );
    }

    /**
     * @param list<RateLimitLog> $rows
     */
    private function buildContribution(string $ip, array $rows): IncidentSourceContribution
    {
        $first = $rows[0];
        $last = $rows[count($rows) - 1];
        $urls = [];
        $uaCounts = [];
        foreach ($rows as $row) {
            $urls[$row->getUrl()] = true;
            $ua = $row->getUserAgent();
            if ($ua !== null && $ua !== '') {
                $uaCounts[$ua] = ($uaCounts[$ua] ?? 0) + 1;
            }
        }

        return new IncidentSourceContribution(
            ip: $ip,
            sourceKey: self::KEY,
            hits: count($rows),
            startedAt: $first->getCreatedAt(),
            endedAt: $last->getCreatedAt(),
            distinctPaths: count($urls),
            distinctUserAgents: count($uaCounts),
            samplePaths: array_slice(array_keys($urls), 0, self::MAX_SAMPLE_URLS),
            userAgentCounts: $uaCounts,
        );
    }
}
