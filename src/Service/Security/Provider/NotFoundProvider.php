<?php declare(strict_types=1);

namespace App\Service\Security\Provider;

use App\Entity\NotFoundLog;
use App\Enum\SecurityEventType;
use App\Repository\NotFoundLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final class NotFoundProvider extends AbstractSecurityProvider
{
    public const string KEY = 'not_found';

    private const int RECENT_PATHS_CAP = 10;
    private const int BLOCK_AT_PROBES = 30;
    private const int BLOCK_AT_ASSET_HITS = 300;

    /** @var list<string> */
    private const array ASSET_PATH_PREFIXES = [
        '/assets/',
        '/media/',
    ];

    /** @var list<string> */
    private const array SUSPICIOUS_PATTERNS = [
        '/.env',
        '/wp-admin',
        '/wp-login',
        '/.git',
        '/phpinfo',
        '/xmlrpc.php',
        '/.aws',
        '/admin.php',
        '/config.php',
        '/setup.php',
        '/.well-known/security',
    ];

    public function __construct(
        CacheItemPoolInterface $securityCachePool,
        LoggerInterface $logger,
        private readonly EntityManagerInterface $em,
        private readonly NotFoundLogRepository $logRepo,
    ) {
        parent::__construct($securityCachePool, $logger);
    }

    #[Override]
    public function getKey(): string
    {
        return self::KEY;
    }

    #[Override]
    public function getPriority(): int
    {
        return 0;
    }

    #[Override]
    protected function handlesType(SecurityEventType $type): bool
    {
        return $type === SecurityEventType::NotFound;
    }

    #[Override]
    protected function processEvent(SecurityEventType $type, Request $request, array $context, string $ip, array $state): array
    {
        $path = $request->getPathInfo();
        $isApi = str_starts_with($path, '/api/');

        $totalHits = (int) ($state['totalHits'] ?? 0) + 1;
        $apiHits = (int) ($state['apiHits'] ?? 0);
        $probeHits = (int) ($state['probeHits'] ?? 0);
        $assetHits = (int) ($state['assetHits'] ?? 0);
        $recentPaths = is_array($state['recentPaths'] ?? null) ? $state['recentPaths'] : [];
        $waveTimestamps = is_array($state['waveTimestamps'] ?? null) ? $state['waveTimestamps'] : [];

        $recentPaths[] = $path;
        if (count($recentPaths) > self::RECENT_PATHS_CAP) {
            $recentPaths = array_slice($recentPaths, -self::RECENT_PATHS_CAP);
        }
        $distinctPaths = count(array_unique($recentPaths));

        $isAsset = false;
        foreach (self::ASSET_PATH_PREFIXES as $prefix) {
            if (!str_starts_with($path, $prefix)) {
                continue;
            }

            $isAsset = true;
            break;
        }

        if ($isAsset) {
            // Asset 404s are normal after a redeploy (stale browser cache, old importmap hashes).
            // They still accumulate threat but at 1/10 the weight of regular probes so that a
            // browser clearing stale assets after a deploy never blocks a real user, while a
            // scanner hammering /assets/* at scale still eventually trips the threshold.
            ++$assetHits;
            $probeWeight = $probeHits * (100 / self::BLOCK_AT_PROBES);
            $assetWeight = $assetHits * (100 / self::BLOCK_AT_ASSET_HITS);
            $threatLevel = (int) min(100, $probeWeight + $assetWeight);
            $summary = sprintf('%d probe 404s, %d asset 404s (distinct paths: %d)', $probeHits, $assetHits, $distinctPaths);
        } elseif ($isApi) {
            ++$apiHits;
            if ($distinctPaths <= 3 && $apiHits <= 2000) {
                $threatLevel = (int) min($apiHits / 20, 30);
            } else {
                $threatLevel = (int) min($apiHits, 80);
            }
            $summary = sprintf('%d API 404s (distinct paths: %d)', $apiHits, $distinctPaths);
        } else {
            ++$probeHits;
            $base = 0;
            foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
                if (!str_contains($path, $pattern)) {
                    continue;
                }

                $base += 5;
                break;
            }
            $probeWeight = $probeHits * (100 / self::BLOCK_AT_PROBES);
            $assetWeight = (int) ($state['assetHits'] ?? 0) * (100 / self::BLOCK_AT_ASSET_HITS);
            $threatLevel = (int) min(100, $base + $probeWeight + $assetWeight);
            $summary = sprintf('%d probe 404s, %d asset 404s (distinct paths: %d)', $probeHits, $assetHits, $distinctPaths);
        }

        $previousThreat = (int) ($state['threatLevel'] ?? 0);
        if (intdiv($threatLevel, 20) > intdiv($previousThreat, 20)) {
            $waveTimestamps[] = time();
            if (count($waveTimestamps) > 5) {
                $waveTimestamps = array_slice($waveTimestamps, -5);
            }
        }

        $details = [
            'totalHits' => $totalHits,
            'apiHits' => $apiHits,
            'probeHits' => $probeHits,
            'assetHits' => $assetHits,
            'distinctPaths' => $distinctPaths,
            'recentPaths' => array_values($recentPaths),
            'waveCount' => count($waveTimestamps),
        ];

        return [
            'state' => [
                'totalHits' => $totalHits,
                'apiHits' => $apiHits,
                'probeHits' => $probeHits,
                'assetHits' => $assetHits,
                'distinctPaths' => $distinctPaths,
                'recentPaths' => array_values($recentPaths),
                'waveTimestamps' => array_values($waveTimestamps),
                'lastSeenAt' => time(),
            ],
            'threatLevel' => $threatLevel,
            'summary' => $summary,
            'details' => $details,
        ];
    }

    #[Override]
    protected function persistLog(Request $request, array $context): void
    {
        try {
            $log = new NotFoundLog();
            $log->setCreatedAt(new DateTimeImmutable());
            $log->setIp($request->getClientIp() ?? '');
            $log->setUrl($request->getPathInfo());
            $log->setUserAgent($request->headers->get('User-Agent'));
            $log->setReferer($request->headers->get('Referer'));

            $this->em->persist($log);
            $this->em->flush();
        } catch (Throwable $e) {
            $this->logger->warning('Failed to persist not-found log: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    #[Override]
    protected function scanLogs(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = $this->logRepo->findFiltered(limit: 1000, since: $from, ip: null, from: $from, to: $to);

        $uniqueIps = [];
        $suspiciousHits = 0;
        foreach ($rows as $row) {
            $ip = $row->getIp();
            if ($ip !== null && $ip !== '') {
                $uniqueIps[$ip] = true;
            }
            $url = (string) $row->getUrl();
            foreach (self::SUSPICIOUS_PATTERNS as $pattern) {
                if (!str_contains($url, $pattern)) {
                    continue;
                }

                ++$suspiciousHits;
                break;
            }
        }

        $threatLevel = (int) min(100, ($suspiciousHits + count($uniqueIps)) / 100);
        $summary = sprintf('%d 404s in window, %d unique IPs, %d suspicious-pattern hits', count($rows), count($uniqueIps), $suspiciousHits);

        return [
            'threatLevel' => $threatLevel,
            'summary' => $summary,
            'details' => [
                'rows' => count($rows),
                'uniqueIps' => count($uniqueIps),
                'suspiciousHits' => $suspiciousHits,
            ],
        ];
    }
}
