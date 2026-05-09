<?php declare(strict_types=1);

namespace App\Service\Security\Incident\Sources;

use App\Entity\NotFoundLog;
use App\Repository\IncidentRepository;
use App\Repository\NotFoundLogRepository;
use App\Service\AppStateService;
use App\Service\Security\Incident\IncidentMerger;
use App\Service\Security\Incident\IncidentSourceContribution;
use App\Service\Security\Incident\IncidentSourceInterface;
use App\Service\Security\Incident\IncidentSourceStats;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;

/**
 * Idempotency invariants - do not change without re-reading the rules:
 *   - The settle window guarantees the aggregator never slices a live series.
 *   - Per-run progress is bounded by BATCH_SIZE; the last consumed NotFoundLog id
 *     is persisted in AppState so the next run resumes from there.
 *   - The per-IP MAX(endedAt) watermark is still consulted to drop rows that
 *     a previous run already absorbed - belt-and-suspenders against double-count.
 */
final readonly class UrlProbingIncidentSource implements IncidentSourceInterface
{
    public const string KEY = IncidentSourceContribution::KEY_PROBING;
    public const string KEY_LAST_PROCESSED_ID = 'security.incident.url_probing.last_processed_log_id';
    public const int GAP_THRESHOLD_MINUTES = 30;
    public const int SETTLE_MINUTES = 60;
    public const int MIN_PROBES_PER_INCIDENT = 30;
    public const int MAX_SAMPLE_URLS = 10;
    public const int BATCH_SIZE = 10000;

    public function __construct(
        private EntityManagerInterface $em,
        private NotFoundLogRepository $notFoundLogRepo,
        private IncidentRepository $incidentRepo,
        private IncidentMerger $merger,
        private AppStateService $appState,
        private ClockInterface $clock,
        private LoggerInterface $logger,
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

        $rows = $this->notFoundLogRepo->findRowsAfterIdUpTo($lastId, $cutoff, self::BATCH_SIZE);
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
            $ip = (string) $row->getIp();
            $rowsByIp[$ip][] = $row;
        }

        $perIpWatermark = $this->incidentRepo->findLastEndedAtPerIp();

        $considered = 0;
        $incidentsTouched = 0;
        $deferredMinId = PHP_INT_MAX;
        $batchFull = count($rows) >= self::BATCH_SIZE;

        foreach ($rowsByIp as $ip => $ipRows) {
            $watermark = $perIpWatermark[$ip] ?? null;
            if ($watermark !== null) {
                $ipRows = array_values(array_filter(
                    $ipRows,
                    static function (NotFoundLog $row) use ($watermark): bool {
                        $createdAt = $row->getCreatedAt();
                        return $createdAt !== null && $createdAt > $watermark;
                    },
                ));
            }
            if ($ipRows === []) {
                continue;
            }

            $considered += count($ipRows);
            $result = $this->processIpSeries($ip, $ipRows, $batchFull);
            $incidentsTouched += $result['incidents'];
            if ($result['deferredMinId'] !== null && $result['deferredMinId'] < $deferredMinId) {
                $deferredMinId = $result['deferredMinId'];
            }
        }

        $newWatermark = $deferredMinId !== PHP_INT_MAX
            ? max($lastId, $deferredMinId - 1)
            : $batchMaxId;

        if ($newWatermark <= $lastId && $batchFull) {
            $this->logger->warning(sprintf(
                'UrlProbingIncidentSource: batch fully deferred (lastId=%d, batchMaxId=%d) - advancing watermark to unblock progress',
                $lastId,
                $batchMaxId,
            ));
            $newWatermark = $batchMaxId;
        }

        $this->em->flush();

        if ($newWatermark > $lastId) {
            $this->appState->set(self::KEY_LAST_PROCESSED_ID, (string) $newWatermark);
        }

        return new IncidentSourceStats(
            sourceKey: self::KEY,
            considered: $considered,
            ipsTouched: count($rowsByIp),
            incidentsTouched: $incidentsTouched,
            lastProcessedId: $newWatermark,
            hasMore: $batchFull,
        );
    }

    /**
     * @param list<NotFoundLog> $ipRows
     * @return array{incidents: int, deferredMinId: ?int}
     */
    private function processIpSeries(string $ip, array $ipRows, bool $batchFull): array
    {
        $gapSeconds = self::GAP_THRESHOLD_MINUTES * 60;
        /** @var list<list<NotFoundLog>> $series */
        $series = [];
        $current = [];
        $previousTs = null;

        foreach ($ipRows as $row) {
            $createdAt = $row->getCreatedAt();
            if ($createdAt === null) {
                continue;
            }
            $ts = $createdAt->getTimestamp();
            if ($previousTs !== null && ($ts - $previousTs) > $gapSeconds) {
                $series[] = $current;
                $current = [];
            }
            $current[] = $row;
            $previousTs = $ts;
        }
        if ($current !== []) {
            $series[] = $current;
        }

        $incidents = 0;
        $deferredMinId = null;
        $totalSeries = count($series);

        foreach ($series as $idx => $seriesRows) {
            $isLastSeries = $idx === $totalSeries - 1;
            if ($isLastSeries && $this->seriesHasContinuation($ip, $seriesRows)) {
                if (!$batchFull) {
                    $firstId = (int) $seriesRows[0]->getId();
                    if ($deferredMinId === null || $firstId < $deferredMinId) {
                        $deferredMinId = $firstId;
                    }
                    continue;
                }
            }

            if (count($seriesRows) < self::MIN_PROBES_PER_INCIDENT) {
                continue;
            }

            $this->merger->merge($this->buildContribution($ip, $seriesRows));
            $incidents++;
        }

        return ['incidents' => $incidents, 'deferredMinId' => $deferredMinId];
    }

    /**
     * @param list<NotFoundLog> $seriesRows
     */
    private function seriesHasContinuation(string $ip, array $seriesRows): bool
    {
        $lastRow = $seriesRows[count($seriesRows) - 1];
        $endedAt = $lastRow->getCreatedAt();
        if ($endedAt === null) {
            return false;
        }

        $next = $this->notFoundLogRepo->findFirstCreatedAtForIpAfter($ip, $endedAt);
        if ($next === null) {
            return false;
        }

        $gap = $next->getTimestamp() - $endedAt->getTimestamp();

        return $gap < (self::GAP_THRESHOLD_MINUTES * 60);
    }

    /**
     * @param list<NotFoundLog> $seriesRows
     */
    private function buildContribution(string $ip, array $seriesRows): IncidentSourceContribution
    {
        $first = $seriesRows[0];
        $last = $seriesRows[count($seriesRows) - 1];

        $urls = [];
        $uaCounts = [];
        foreach ($seriesRows as $row) {
            $url = (string) $row->getUrl();
            $urls[$url] = true;
            $ua = $row->getUserAgent();
            if ($ua !== null && $ua !== '') {
                $uaCounts[$ua] = ($uaCounts[$ua] ?? 0) + 1;
            }
        }

        $sampleUrls = array_slice(array_keys($urls), 0, self::MAX_SAMPLE_URLS);

        return new IncidentSourceContribution(
            ip: $ip,
            sourceKey: self::KEY,
            hits: count($seriesRows),
            startedAt: $first->getCreatedAt() ?? $this->clock->now(),
            endedAt: $last->getCreatedAt() ?? $this->clock->now(),
            distinctPaths: count($urls),
            distinctUserAgents: count($uaCounts),
            samplePaths: array_values($sampleUrls),
            userAgentCounts: $uaCounts,
        );
    }
}
