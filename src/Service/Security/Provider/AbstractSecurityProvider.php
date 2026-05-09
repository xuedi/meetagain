<?php declare(strict_types=1);

namespace App\Service\Security\Provider;

use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Service\Security\ProviderReport;
use App\Service\Security\SecurityProviderInterface;
use DateTimeImmutable;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;
use Throwable;

abstract class AbstractSecurityProvider implements SecurityProviderInterface
{
    private const int STATE_TTL_SECONDS = 86_400;

    public function __construct(
        protected readonly CacheItemPoolInterface $securityCachePool,
        protected readonly LoggerInterface $logger,
    ) {}

    abstract public function getKey(): string;

    abstract public function getPriority(): int;

    abstract protected function handlesType(SecurityEventType $type): bool;

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $state
     * @return array{state: array<string, mixed>, threatLevel: int, summary: string, details: array<string, mixed>}
     */
    abstract protected function processEvent(
        SecurityEventType $type,
        Request $request,
        array $context,
        string $ip,
        array $state,
    ): array;

    /**
     * @return array{threatLevel: int, summary: string, details: array<string, mixed>}
     */
    abstract protected function scanLogs(DateTimeImmutable $from, DateTimeImmutable $to): array;

    /**
     * @param array<string, mixed> $context
     */
    protected function persistLog(Request $request, array $context): void
    {
        // default no-op; detection providers override
    }

    protected function resolveStateKey(string $sessionId, string $ip): string
    {
        return $sessionId;
    }

    #[Override]
    public function handles(SecurityEventType $type): bool
    {
        return $this->handlesType($type);
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
        $state = $this->loadState($stateKey);

        $alreadyBlocked = ($state['recommendation'] ?? null) === SecurityRecommendation::Block->value
            || ($state['recommendation'] ?? null) === SecurityRecommendation::BlockShortCircuit->value;

        if ($readOnly || !$this->handlesType($type) || $alreadyBlocked) {
            $details = $state['details'] ?? [];
            return $this->buildReport(
                threatLevel: (int) ($state['threatLevel'] ?? 0),
                summary: (string) ($state['summary'] ?? 'No issues'),
                details: is_array($details) ? $details : [],
            );
        }

        $result = $this->processEvent($type, $request, $context, $ip, $state);

        $newState = $result['state'];
        $newState['threatLevel'] = $result['threatLevel'];
        $newState['summary'] = $result['summary'];
        $newState['details'] = $result['details'];

        $report = $this->buildReport($result['threatLevel'], $result['summary'], $result['details']);
        $newState['recommendation'] = $report->recommendation->value;

        $this->saveState($stateKey, $newState);
        $this->persistLog($request, $context);

        return $report;
    }

    #[Override]
    final public function scanRetrospective(DateTimeImmutable $from, DateTimeImmutable $to): ProviderReport
    {
        $result = $this->scanLogs($from, $to);

        return $this->buildReport($result['threatLevel'], $result['summary'], $result['details']);
    }

    /**
     * @return array<string, mixed>
     */
    protected function loadState(string $stateKey): array
    {
        $cacheKey = $this->cacheKey($stateKey);
        try {
            $item = $this->securityCachePool->getItem($cacheKey);
            if (!$item->isHit()) {
                return [];
            }
            $value = $item->get();
        } catch (Throwable $e) {
            $this->logger->warning('Failed to load security provider state: ' . $e->getMessage(), [
                'exception' => $e,
                'cacheKey' => $cacheKey,
            ]);
            return [];
        }

        return is_array($value) ? $value : [];
    }

    /**
     * @param array<string, mixed> $state
     */
    protected function saveState(string $stateKey, array $state): void
    {
        $cacheKey = $this->cacheKey($stateKey);
        try {
            $item = $this->securityCachePool->getItem($cacheKey);
            $item->set($state);
            $item->expiresAfter(self::STATE_TTL_SECONDS);
            $this->securityCachePool->save($item);
        } catch (Throwable $e) {
            $this->logger->warning('Failed to save security provider state: ' . $e->getMessage(), [
                'exception' => $e,
                'cacheKey' => $cacheKey,
            ]);
        }
    }

    private function cacheKey(string $stateKey): string
    {
        $sanitized = preg_replace('/[^A-Za-z0-9_.\-]/', '_', $stateKey) ?? 'unknown';

        return 'security_provider_' . $this->getKey() . '_' . $sanitized;
    }
}
