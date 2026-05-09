<?php

declare(strict_types=1);

namespace App\Service\Security;

use App\Entity\Incident;
use App\Enum\IncidentSeverity;
use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Service\AppStateService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

readonly class SecurityService
{
    public const string KEY_LAST_RETROSPECTIVE_RUN = 'security.retrospective_scan.last_run';
    public const int BLOCK_TTL_SECONDS = 14_400;
    public const string BLOCK_DURATION_LABEL = '4h';

    /**
     * @param iterable<SecurityProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(SecurityProviderInterface::class)]
        private iterable $providers,
        private BlockedSessionStore $blockStore,
        private EntityManagerInterface $em,
        private AppStateService $appState,
        private ClockInterface $clock,
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<string, mixed> $context
     */
    public function event(SecurityEventType $type, Request $request, array $context = []): void
    {
        $ip = $request->getClientIp() ?? '';
        $sessionId = $this->resolveSessionId($request, $ip);

        if ($ip !== '' && $this->blockStore->isIpBlocked($ip)) {
            return;
        }
        if ($this->blockStore->isSessionBlocked($sessionId)) {
            return;
        }

        $providers = $this->sortedProviders();

        $reports = [];
        $readOnly = false;
        $shortCircuited = false;

        foreach ($providers as $provider) {
            $report = $provider->observe($type, $request, $context, $sessionId, $ip, $readOnly);
            $reports[] = $report;

            if ($report->recommendation === SecurityRecommendation::BlockShortCircuit) {
                $shortCircuited = true;
                $readOnly = true;
            }
        }

        if ($shortCircuited) {
            if ($ip !== '') {
                $this->blockStore->blockIp($ip, $this->buildSnapshot('fuse', $reports), self::BLOCK_TTL_SECONDS);
            }
            return;
        }

        $blockingReport = null;
        foreach ($reports as $report) {
            if ($report->recommendation !== SecurityRecommendation::Block) {
                continue;
            }

            $blockingReport = $report;
            break;
        }
        if ($blockingReport === null) {
            return;
        }

        try {
            $this->writeIncident($request, $sessionId, $ip, $blockingReport->providerKey, $reports);
        } catch (Throwable $e) {
            $this->logger->warning('Failed to write security incident: ' . $e->getMessage(), [
                'exception' => $e,
            ]);
        }

        $snapshot = $this->buildSnapshot('provider', $reports);
        $this->blockStore->blockSession($sessionId, $snapshot, self::BLOCK_TTL_SECONDS);
        if ($ip !== '') {
            $this->blockStore->blockIp($ip, $snapshot, self::BLOCK_TTL_SECONDS);
        }
    }

    /**
     * @return list<ProviderReport>
     */
    public function runRetrospectiveScan(): array
    {
        $lastRunRaw = $this->appState->get(self::KEY_LAST_RETROSPECTIVE_RUN);
        $now = $this->clock->now();
        $from = $lastRunRaw !== null
            ? new DateTimeImmutable()->setTimestamp((int) $lastRunRaw)
            : $now->modify('-1 hour');
        $to = $now;

        // TODO(2026-05-09) Aggregated reports are currently observation-only:
        // hook in admin notification dispatch / threshold alerting once the
        // notification routing for high retrospective threat levels is decided.
        $reports = [];
        foreach ($this->sortedProviders() as $provider) {
            $reports[] = $provider->scanRetrospective($from, $to);
        }

        $this->appState->set(self::KEY_LAST_RETROSPECTIVE_RUN, (string) $now->getTimestamp());

        return $reports;
    }

    public function isSessionBlocked(string $sessionId): bool
    {
        return $this->blockStore->isSessionBlocked($sessionId);
    }

    public function isIpBlocked(string $ip): bool
    {
        return $this->blockStore->isIpBlocked($ip);
    }

    private function resolveSessionId(Request $request, string $ip): string
    {
        $sessionId = $this->safeReadSessionId($request);
        if ($sessionId !== null) {
            return $sessionId;
        }

        return 'ip:' . ($ip !== '' ? $ip : 'unknown');
    }

    private function safeReadSessionId(Request $request): ?string
    {
        try {
            if (!$request->hasSession(true)) {
                return null;
            }
            $session = $request->getSession();
            if (!$session->isStarted()) {
                return null;
            }
            $id = $session->getId();

            return $id !== '' ? $id : null;
        } catch (Throwable $e) {
            $this->logger->debug('Session read failed in SecurityService: ' . $e->getMessage());
            return null;
        }
    }

    /**
     * @return list<SecurityProviderInterface>
     */
    private function sortedProviders(): array
    {
        $list = [];
        foreach ($this->providers as $provider) {
            $list[] = $provider;
        }
        usort(
            $list,
            static fn(
                SecurityProviderInterface $a,
                SecurityProviderInterface $b,
            ): int => $b->getPriority() <=> $a->getPriority(),
        );

        return $list;
    }

    /**
     * @param list<ProviderReport> $reports
     * @return array<string, mixed>
     */
    private function buildSnapshot(string $reason, array $reports): array
    {
        $serialised = [];
        $maxThreat = 0;
        $primaryProvider = null;
        foreach ($reports as $report) {
            $serialised[] = $report->toArray();
            if ($report->recommendation === SecurityRecommendation::Block && $primaryProvider === null) {
                $primaryProvider = $report->providerKey;
            }
            if ($report->recommendation === SecurityRecommendation::BlockShortCircuit && $primaryProvider === null) {
                $primaryProvider = $report->providerKey;
            }
            if ($report->threatLevel > $maxThreat) {
                $maxThreat = $report->threatLevel;
            }
        }

        return [
            'reason' => $reason,
            'blockedAt' => $this->clock->now()->getTimestamp(),
            'primaryProvider' => $primaryProvider,
            'maxThreatLevel' => $maxThreat,
            'reports' => $serialised,
        ];
    }

    /**
     * @param list<ProviderReport> $reports
     */
    private function writeIncident(
        Request $request,
        string $sessionId,
        string $ip,
        string $triggeredBy,
        array $reports,
    ): void {
        $maxThreat = 0;
        $serialised = [];
        foreach ($reports as $report) {
            $serialised[] = $report->toArray();
            if ($report->threatLevel > $maxThreat) {
                $maxThreat = $report->threatLevel;
            }
        }

        $now = $this->clock->now();

        $incident = new Incident();
        $incident->setIp($ip);
        $incident->setSessionId($sessionId);
        $incident->setTriggeredBy($triggeredBy);
        $incident->setSeverity($this->severityFor($maxThreat));
        $incident->setProviderReports($serialised);
        $incident->setBlockedUntilDescription(self::BLOCK_DURATION_LABEL);
        $incident->setUserAgent($request->headers->get('User-Agent'));
        $incident->setStartedAt($now);
        $incident->setEndedAt($now);
        $incident->setCreatedAt($now);
        $incident->setUpdatedAt($now);

        $this->em->persist($incident);
        $this->em->flush();
    }

    private function severityFor(int $threatLevel): IncidentSeverity
    {
        return match (true) {
            $threatLevel >= 90 => IncidentSeverity::Critical,
            $threatLevel >= 70 => IncidentSeverity::High,
            $threatLevel >= 40 => IncidentSeverity::Medium,
            default => IncidentSeverity::Low,
        };
    }
}
