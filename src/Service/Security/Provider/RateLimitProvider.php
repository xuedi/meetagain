<?php declare(strict_types=1);

namespace App\Service\Security\Provider;

use App\Entity\RateLimitLog;
use App\Enum\SecurityEventType;
use App\Repository\RateLimitLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final class RateLimitProvider extends AbstractSecurityProvider
{
    public const string KEY = 'rate_limit';

    public function __construct(
        CacheItemPoolInterface $securityCachePool,
        LoggerInterface $logger,
        private readonly EntityManagerInterface $em,
        private readonly RateLimitLogRepository $logRepo,
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
        return $type === SecurityEventType::RateLimit;
    }

    #[Override]
    protected function processEvent(
        SecurityEventType $type,
        Request $request,
        array $context,
        string $ip,
        array $state,
    ): array {
        $limiter = is_string($context['limiter'] ?? null) ? $context['limiter'] : 'unknown';

        $totalHits = (int) ($state['totalHits'] ?? 0) + 1;
        $byLimiter = is_array($state['byLimiter'] ?? null) ? $state['byLimiter'] : [];
        $byLimiter[$limiter] = (int) ($byLimiter[$limiter] ?? 0) + 1;
        $hitsThisLimiter = $byLimiter[$limiter];

        $threatLevel = $this->scoreFor($limiter, $hitsThisLimiter);
        $summary = sprintf('%d rate-limit hits (%s: %d)', $totalHits, $limiter, $hitsThisLimiter);
        $details = [
            'totalHits' => $totalHits,
            'byLimiter' => $byLimiter,
            'lastLimiter' => $limiter,
        ];

        return [
            'state' => [
                'totalHits' => $totalHits,
                'byLimiter' => $byLimiter,
                'lastSeenAt' => time(),
            ],
            'threatLevel' => $threatLevel,
            'summary' => $summary,
            'details' => $details,
        ];
    }

    private function scoreFor(string $limiter, int $hits): int
    {
        if ($limiter === 'login_throttling') {
            return 100;
        }
        if ($limiter === 'support') {
            return (int) min(60, $hits * 5);
        }
        if (str_starts_with($limiter, 'api_')) {
            if ($hits <= 2) {
                return 0;
            }
            return (int) min(100, $hits * 10);
        }

        return (int) min(80, $hits * 8);
    }

    #[Override]
    protected function persistLog(Request $request, array $context): void
    {
        try {
            $limiter = is_string($context['limiter'] ?? null) ? $context['limiter'] : 'unknown';
            $userIdentifier = is_string($context['userIdentifier'] ?? null) ? $context['userIdentifier'] : null;

            $log = new RateLimitLog();
            $log->setCreatedAt(new DateTimeImmutable());
            $log->setIp($request->getClientIp() ?? '');
            $log->setUrl($request->getRequestUri());
            $log->setLimiter($limiter);
            $log->setUserAgent($request->headers->get('User-Agent'));
            if ($userIdentifier !== null && $userIdentifier !== '') {
                $log->setUserIdentifier($userIdentifier);
            }

            $this->em->persist($log);
            $this->em->flush();
        } catch (Throwable $e) {
            $this->logger->warning('Failed to persist rate-limit log: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    #[Override]
    protected function scanLogs(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = $this->logRepo->findFiltered(limit: 1000, since: $from, ip: null, from: $from, to: $to);
        $byLimiter = [];
        $hasLoginThrottling = false;
        foreach ($rows as $row) {
            $limiter = $row->getLimiter();
            $byLimiter[$limiter] = ($byLimiter[$limiter] ?? 0) + 1;
            if ($limiter === 'login_throttling') {
                $hasLoginThrottling = true;
            }
        }

        $threatLevel = (int) min(100, count($rows) + ($hasLoginThrottling ? 30 : 0));
        $summary = sprintf('%d rate-limit events across %d limiters', count($rows), count($byLimiter));

        return [
            'threatLevel' => $threatLevel,
            'summary' => $summary,
            'details' => [
                'rows' => count($rows),
                'byLimiter' => $byLimiter,
                'loginThrottling' => $hasLoginThrottling,
            ],
        ];
    }
}
