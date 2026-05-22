<?php declare(strict_types=1);

namespace App\Service\Security\Provider;

use App\Entity\AccessDeniedLog;
use App\Entity\User;
use App\Enum\SecurityEventType;
use App\Repository\AccessDeniedLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

final class AccessDeniedProvider extends AbstractSecurityProvider
{
    public const string KEY = 'access_denied';

    private const int RECENT_PATHS_CAP = 10;

    public function __construct(
        CacheItemPoolInterface $securityCachePool,
        LoggerInterface $logger,
        private readonly EntityManagerInterface $em,
        private readonly AccessDeniedLogRepository $logRepo,
        private readonly Security $security,
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
        return $type === SecurityEventType::AccessDenied;
    }

    #[Override]
    protected function processEvent(
        SecurityEventType $type,
        Request $request,
        array $context,
        string $ip,
        array $state,
    ): array {
        $reason = is_string($context['reason'] ?? null) ? $context['reason'] : 'firewall';
        $now = time();

        $hits = (int) ($state['hits'] ?? 0) + 1;
        $byReason = is_array($state['byReason'] ?? null) ? $state['byReason'] : [];
        $byReason[$reason] = (int) ($byReason[$reason] ?? 0) + 1;

        $recentPaths = is_array($state['recentPaths'] ?? null) ? $state['recentPaths'] : [];
        $recentPaths[] = $request->getPathInfo();
        if (count($recentPaths) > self::RECENT_PATHS_CAP) {
            $recentPaths = array_slice($recentPaths, -self::RECENT_PATHS_CAP);
        }
        $distinctPaths = count(array_unique($recentPaths));

        $firstSeenAt = (int) ($state['firstSeenAt'] ?? $now);

        $threatLevel = 0;
        if ($hits <= 10) {
            $threatLevel = (int) min(20, $hits * 2);
        } else {
            $elapsed = max(1, $now - $firstSeenAt);
            $perMinute = ($hits / $elapsed) * 60;
            $threatLevel = (int) min(80, $hits * 2);
            if ($perMinute > 6) {
                $threatLevel = (int) min(100, $threatLevel + 50);
            }
        }

        if (($byReason['csrf'] ?? 0) > 0) {
            $threatLevel = (int) min(100, $threatLevel + (5 * (int) $byReason['csrf']));
        }

        if ($distinctPaths >= 8 && $hits >= 15) {
            $threatLevel = 100;
        }

        $summary = sprintf(
            '%d access-denied (distinct paths: %d, csrf: %d)',
            $hits,
            $distinctPaths,
            (int) ($byReason['csrf'] ?? 0),
        );
        $details = [
            'hits' => $hits,
            'byReason' => $byReason,
            'distinctPaths' => $distinctPaths,
            'recentPaths' => array_values($recentPaths),
        ];

        return [
            'state' => [
                'hits' => $hits,
                'byReason' => $byReason,
                'distinctPaths' => $distinctPaths,
                'recentPaths' => array_values($recentPaths),
                'firstSeenAt' => $firstSeenAt,
                'lastSeenAt' => $now,
            ],
            'threatLevel' => $threatLevel,
            'summary' => $summary,
            'details' => $details,
        ];
    }

    public static function resolveReason(Throwable $exception, bool $isHttpAccessDenied): string
    {
        $message = $exception->getMessage();
        if (str_starts_with($message, 'Invalid CSRF')) {
            return 'csrf';
        }
        if ($isHttpAccessDenied) {
            return 'controller';
        }
        $previous = $exception->getPrevious();
        if ($previous !== null && str_contains($previous->getMessage(), 'voter')) {
            return 'voter';
        }
        if (str_contains($message, 'voter') || str_contains($message, 'Access Denied by')) {
            return 'voter';
        }

        return 'firewall';
    }

    #[Override]
    protected function persistLog(Request $request, array $context): void
    {
        try {
            $reason = is_string($context['reason'] ?? null) ? $context['reason'] : 'firewall';

            $log = new AccessDeniedLog();
            $log->setCreatedAt(new DateTimeImmutable());
            $log->setIp($request->getClientIp() ?? '');
            $log->setUrl($request->getRequestUri());
            $log->setReason($reason);
            $log->setUserAgent($request->headers->get('User-Agent'));

            $user = $this->security->getUser();
            if ($user instanceof User) {
                $log->setUser($user);
            }

            $this->em->persist($log);
            $this->em->flush();
        } catch (Throwable $e) {
            $this->logger->warning('Failed to persist access-denied log: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }
    }

    #[Override]
    protected function scanLogs(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = $this->logRepo->findFiltered(limit: 1000, since: $from, ip: null, from: $from, to: $to);
        $byReason = [];
        foreach ($rows as $row) {
            $reason = $row->getReason();
            $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
        }
        $threatLevel = (int) min(100, count($rows));
        $summary = sprintf('%d access-denied events across %d reasons', count($rows), count($byReason));

        return [
            'threatLevel' => $threatLevel,
            'summary' => $summary,
            'details' => [
                'rows' => count($rows),
                'byReason' => $byReason,
            ],
        ];
    }
}
