<?php declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\NotFoundLog;
use App\Entity\UrlProbingIncident;
use App\Repository\NotFoundLogRepository;
use App\Repository\UrlProbingIncidentRepository;
use DateTimeImmutable;
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
 *   - The per-IP MAX(endedAt) watermark guarantees the same series is never
 *     aggregated twice on subsequent runs.
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

    public function __construct(
        private EntityManagerInterface $em,
        private NotFoundLogRepository $notFoundLogRepo,
        private UrlProbingIncidentRepository $incidentRepo,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @return array{considered: int, ipsProcessed: int, incidents: int, dropped: int}
     */
    public function aggregate(): array
    {
        $now = $this->clock->now();
        $cutoff = $now->modify('-' . self::SETTLE_MINUTES . ' minutes');
        $epoch = new DateTimeImmutable('@0');

        $lastEndedAtPerIp = $this->incidentRepo->findLastEndedAtPerIp();

        $globalAfter = $epoch;
        if ($lastEndedAtPerIp !== []) {
            $minTimestamp = PHP_INT_MAX;
            foreach ($lastEndedAtPerIp as $ts) {
                $minTimestamp = min($minTimestamp, $ts->getTimestamp());
            }
            $globalAfter = (new DateTimeImmutable())->setTimestamp($minTimestamp);
        }

        $ips = $this->notFoundLogRepo->findIpsWithRowsBetween($globalAfter, $cutoff);

        $considered = 0;
        $incidentsCreated = 0;
        $dropped = 0;

        foreach ($ips as $ip) {
            $watermark = $lastEndedAtPerIp[$ip] ?? $epoch;
            try {
                $rows = $this->notFoundLogRepo->findRowsForIpBetween($ip, $watermark, $cutoff);
                if ($rows === []) {
                    continue;
                }
                $considered += count($rows);

                $this->em->beginTransaction();
                try {
                    $result = $this->processIpRows($ip, $rows, $cutoff);
                    $incidentsCreated += $result['incidents'];
                    $dropped += $result['dropped'];
                    $this->em->flush();
                    $this->em->commit();
                } catch (Throwable $e) {
                    $this->em->rollback();
                    throw $e;
                }
            } catch (Throwable $e) {
                $this->logger->warning(
                    sprintf('UrlProbingAggregator failed for IP %s: %s', $ip, $e->getMessage()),
                    ['exception' => $e],
                );
            }
        }

        return [
            'considered' => $considered,
            'ipsProcessed' => count($ips),
            'incidents' => $incidentsCreated,
            'dropped' => $dropped,
        ];
    }

    /**
     * @param list<NotFoundLog> $rows
     * @return array{incidents: int, dropped: int}
     */
    private function processIpRows(string $ip, array $rows, DateTimeImmutable $cutoff): array
    {
        $gapSeconds = self::GAP_THRESHOLD_MINUTES * 60;
        /** @var list<list<NotFoundLog>> $series */
        $series = [];
        $current = [];
        $previousTs = null;

        foreach ($rows as $row) {
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
        $totalSeries = count($series);

        foreach ($series as $idx => $seriesRows) {
            $isLastSeries = $idx === $totalSeries - 1;
            if ($isLastSeries && !$this->isSeriesClosed($ip, $seriesRows, $cutoff)) {
                continue;
            }

            if (count($seriesRows) < self::MIN_PROBES_PER_INCIDENT) {
                $dropped++;
                continue;
            }

            $incident = $this->buildIncident($ip, $seriesRows);
            $this->em->persist($incident);
            $incidents++;
        }

        return ['incidents' => $incidents, 'dropped' => $dropped];
    }

    /**
     * @param list<NotFoundLog> $seriesRows
     */
    private function isSeriesClosed(string $ip, array $seriesRows, DateTimeImmutable $cutoff): bool
    {
        $lastRow = $seriesRows[count($seriesRows) - 1];
        $endedAt = $lastRow->getCreatedAt();
        if ($endedAt === null) {
            return true;
        }

        $firstAfterCutoff = $this->notFoundLogRepo->findFirstCreatedAtForIpAfter($ip, $cutoff);
        if ($firstAfterCutoff === null) {
            return true;
        }

        $gap = $firstAfterCutoff->getTimestamp() - $endedAt->getTimestamp();

        return $gap >= (self::GAP_THRESHOLD_MINUTES * 60);
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
