<?php declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\NotFoundLog;
use App\Entity\UrlProbingIncident;
use App\Repository\NotFoundLogRepository;
use App\Repository\UrlProbingIncidentRepository;
use App\Service\AppStateService;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Throwable;

/**
 * Rolls raw 404 firehose rows from logs_not_found into one-row-per-series
 * UrlProbingIncident records.
 *
 * Idempotency invariants - do not change without re-reading the rules:
 *   - The settle window guarantees the aggregator never slices a live series.
 *   - Per-run progress is bounded by BATCH_SIZE; the last consumed NotFoundLog id
 *     is persisted in AppState so the next run resumes from there. Without this
 *     bound, one click on "Aggregate" had to scan every IP-series in the entire
 *     firehose, which timed out PHP at ~200k rows.
 *   - The per-IP MAX(endedAt) watermark is still consulted to drop rows that
 *     a previous run already absorbed - belt-and-suspenders against double-count
 *     when the id watermark is rewound (e.g. a deferred series re-fetch).
 *   - There is no FK between logs_not_found and logs_url_probing_incident, so
 *     pruning the firehose only affects what future runs would see; already
 *     written incidents are unaffected.
 */
readonly class UrlProbingAggregator
{
    public const int GAP_THRESHOLD_MINUTES = 30;
    public const int SETTLE_MINUTES = 60;
    public const int MIN_PROBES_PER_INCIDENT = 30;
    public const int MAX_SAMPLE_URLS = 10;
    public const int BATCH_SIZE = 10000;
    public const string KEY_LAST_PROCESSED_ID = 'security.url_probing.last_processed_log_id';

    public function __construct(
        private EntityManagerInterface $em,
        private NotFoundLogRepository $notFoundLogRepo,
        private UrlProbingIncidentRepository $incidentRepo,
        private AppStateService $appState,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array{considered: int, ipsProcessed: int, incidents: int, dropped: int, lastProcessedId: int, hasMore: bool}
     */
    public function aggregate(): array
    {
        $now = $this->clock->now();
        $cutoff = $now->modify('-' . self::SETTLE_MINUTES . ' minutes');
        $lastId = (int) ($this->appState->get(self::KEY_LAST_PROCESSED_ID) ?? '0');

        $rows = $this->notFoundLogRepo->findRowsAfterIdUpTo($lastId, $cutoff, self::BATCH_SIZE);
        if ($rows === []) {
            return [
                'considered' => 0,
                'ipsProcessed' => 0,
                'incidents' => 0,
                'dropped' => 0,
                'lastProcessedId' => $lastId,
                'hasMore' => false,
            ];
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
        $incidentsCreated = 0;
        $dropped = 0;
        $deferredMinId = PHP_INT_MAX;
        $batchFull = count($rows) >= self::BATCH_SIZE;

        $this->em->beginTransaction();
        try {
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
                $incidentsCreated += $result['incidents'];
                $dropped += $result['dropped'];
                if ($result['deferredMinId'] !== null && $result['deferredMinId'] < $deferredMinId) {
                    $deferredMinId = $result['deferredMinId'];
                }
            }

            $newWatermark = $deferredMinId !== PHP_INT_MAX
                ? max($lastId, $deferredMinId - 1)
                : $batchMaxId;

            // Anti-stall: if the entire batch is one or more deferred series and
            // the watermark would not move, force the deferral resolution by
            // accepting the partial series on the *next* run; here we still
            // advance past the batch so we don't loop forever on the same rows.
            if ($newWatermark <= $lastId && $batchFull) {
                $this->logger->warning(sprintf(
                    'UrlProbingAggregator: batch fully deferred (lastId=%d, batchMaxId=%d) - advancing watermark to unblock progress',
                    $lastId,
                    $batchMaxId,
                ));
                $newWatermark = $batchMaxId;
            }

            $this->em->flush();
            $this->em->commit();
        } catch (Throwable $e) {
            $this->em->rollback();
            $this->logger->error(
                'UrlProbingAggregator failed: ' . $e->getMessage(),
                ['exception' => $e],
            );
            throw $e;
        }

        if ($newWatermark > $lastId) {
            $this->appState->set(self::KEY_LAST_PROCESSED_ID, (string) $newWatermark);
        }

        return [
            'considered' => $considered,
            'ipsProcessed' => count($rowsByIp),
            'incidents' => $incidentsCreated,
            'dropped' => $dropped,
            'lastProcessedId' => $newWatermark,
            'hasMore' => $batchFull,
        ];
    }

    /**
     * @param list<NotFoundLog> $ipRows
     * @return array{incidents: int, dropped: int, deferredMinId: ?int}
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
        $dropped = 0;
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
                // Batch full + deferral would stall progress: accept partial series.
            }

            if (count($seriesRows) < self::MIN_PROBES_PER_INCIDENT) {
                $dropped++;
                continue;
            }

            $this->em->persist($this->buildIncident($ip, $seriesRows));
            $incidents++;
        }

        return ['incidents' => $incidents, 'dropped' => $dropped, 'deferredMinId' => $deferredMinId];
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
    private function buildIncident(string $ip, array $seriesRows): UrlProbingIncident
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

        $modeUa = null;
        if ($uaCounts !== []) {
            arsort($uaCounts);
            $modeUa = (string) array_key_first($uaCounts);
        }

        $incident = new UrlProbingIncident();
        $incident->setIp($ip);
        $incident->setStartedAt($first->getCreatedAt() ?? $this->clock->now());
        $incident->setEndedAt($last->getCreatedAt() ?? $this->clock->now());
        $incident->setProbeCount(count($seriesRows));
        $incident->setDistinctUrlCount(count($urls));
        $incident->setUserAgent($modeUa);
        $incident->setSampleUrls(array_values($sampleUrls));
        $incident->setCreatedAt($this->clock->now());

        return $incident;
    }
}
