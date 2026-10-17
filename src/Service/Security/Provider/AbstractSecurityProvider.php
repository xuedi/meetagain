<?php declare(strict_types=1);

namespace App\Service\Security\Provider;

use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Service\Cache\AtomicStoreInterface;
use App\Service\Security\ProviderReport;
use App\Service\Security\SecurityProviderInterface;
use DateTimeImmutable;
use Override;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

abstract readonly class AbstractSecurityProvider implements SecurityProviderInterface
{
    private const int STATE_TTL_SECONDS = 86_400;

    public function __construct(
        protected AtomicStoreInterface $store,
        protected LoggerInterface $logger,
    ) {}

    abstract public function getKey(): string;

    abstract public function getPriority(): int;

    #[Override]
    public function handles(SecurityEventType $type): bool
    {
        return $this->handlesType($type);
    }

    #[Override]
    final public function observe(
        SecurityEventType $type,
        Request $request,
        array $context,
        string $sessionId,
        string $ip,
        bool $readOnly = false,
    ): ProviderReport {
        $stateKey = $this->resolveStateKey($sessionId, $ip);
        if ($readOnly || !$this->handlesType($type)) {
            return $this->reportFromState($this->loadState($stateKey));
        }

        $report = null;
        $processed = false;
        $process = function (array $state) use ($type, $request, $context, $ip, &$report, &$processed): ?array {
            if ($this->isBlocked($state)) {
                $report = $this->reportFromState($state);
                $processed = false;

                return null;
            }

            $result = $this->processEvent($type, $request, $context, $ip, $state);
            $report = $this->buildReport($result['threatLevel'], $result['summary'], $result['details']);
            $processed = true;

            return [
                ...$result['state'],
                'threatLevel' => $result['threatLevel'],
                'summary' => $result['summary'],
                'details' => $result['details'],
                'recommendation' => $report->recommendation->value,
            ];
        };

        try {
            $this->store->update($this->stateStoreKey($stateKey), self::STATE_TTL_SECONDS, $process);
            if ($processed) {
                $this->store->indexAdd($this->indexStoreKey(), $stateKey, time() + self::STATE_TTL_SECONDS, self::STATE_TTL_SECONDS * 2);
            }
        } catch (Throwable $e) {
            $this->logger->warning('Failed to update security provider state: ' . $e->getMessage(), [
                'exception' => $e,
                'providerKey' => $this->getKey(),
            ]);
            if ($report === null) {
                $process([]);
            }
        }

        if ($processed) {
            $this->persistLog($request, $context);
        }

        return $report ?? $this->reportFromState([]);
    }

    #[Override]
    final public function scanRetrospective(DateTimeImmutable $from, DateTimeImmutable $to): ProviderReport
    {
        $result = $this->scanLogs($from, $to);

        return $this->buildReport($result['threatLevel'], $result['summary'], $result['details']);
    }

    public function clearAllState(): void
    {
        try {
            $stateKeys = array_keys($this->store->indexEntries($this->indexStoreKey()));
            $this->store->delete(...[
                ...array_map($this->stateStoreKey(...), $stateKeys),
                $this->indexStoreKey(),
            ]);
        } catch (Throwable $e) {
            $this->logger->warning('Failed to clear security provider state: ' . $e->getMessage(), [
                'exception' => $e,
                'providerKey' => $this->getKey(),
            ]);
        }
    }

    abstract protected function handlesType(SecurityEventType $type): bool;

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $state
     * @return array{state: array<string, mixed>, threatLevel: int, summary: string, details: array<string, mixed>}
     */
    abstract protected function processEvent(SecurityEventType $type, Request $request, array $context, string $ip, array $state): array;

    /**
     * @return array{threatLevel: int, summary: string, details: array<string, mixed>}
     */
    abstract protected function scanLogs(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @param array<string, mixed> $context
     */
    protected function persistLog(Request $request, array $context): void {}

    protected function resolveStateKey(string $sessionId, string $ip): string
    {
        return $sessionId;
    }

    /**
     * @param array<string, mixed> $details
     */
    protected function buildReport(int $threatLevel, string $summary, array $details = []): ProviderReport
    {
        $recommendation = $threatLevel >= 100 ? SecurityRecommendation::Block : SecurityRecommendation::Handled;

        return new ProviderReport(
            providerKey: $this->getKey(),
            threatLevel: $threatLevel,
            summary: $summary,
            recommendation: $recommendation,
            details: $details,
        );
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadState(string $stateKey): array
    {
        try {
            return $this->store->read($this->stateStoreKey($stateKey)) ?? [];
        } catch (Throwable $e) {
            $this->logger->warning('Failed to load security provider state: ' . $e->getMessage(), [
                'exception' => $e,
                'providerKey' => $this->getKey(),
            ]);

            return [];
        }
    }

    /**
     * @param array<string, mixed> $state
     */
    private function isBlocked(array $state): bool
    {
        return SecurityRecommendation::tryFrom((string) ($state['recommendation'] ?? ''))?->isBlocking() ?? false;
    }

    /**
     * @param array<string, mixed> $state
     */
    private function reportFromState(array $state): ProviderReport
    {
        $details = $state['details'] ?? [];

        return $this->buildReport(
            threatLevel: (int) ($state['threatLevel'] ?? 0),
            summary: (string) ($state['summary'] ?? 'No issues'),
            details: is_array($details) ? $details : [],
        );
    }

    private function stateStoreKey(string $stateKey): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $stateKey) ?? 'unknown';

        return 'security:state:' . $this->getKey() . ':' . $sanitized;
    }

    private function indexStoreKey(): string
    {
        return 'security:index:' . $this->getKey();
    }
}
