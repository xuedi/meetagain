<?php declare(strict_types=1);

namespace App\Service\Security\Provider;

use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Repository\SecurityMeasureLogRepository;
use App\Service\Security\ProviderReport;
use DateTimeImmutable;
use Override;
use Psr\Cache\CacheItemPoolInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\Request;

final class FormMeasureProvider extends AbstractSecurityProvider
{
    public const string KEY = 'form_measure';
    public const string LOGIN_CONTEXT = 'app_login';
    private const int SCAN_LIMIT = 1000;

    /** @var array<string, int> */
    private const array FORM_WEIGHTS = [
        'invalid_stamp' => 100,
        'invalid_proof' => 100,
        'filled' => 60,
        'missing_stamp' => 60,
        'too_fast' => 40,
        'nonce_reused' => 30,
        'missing_proof' => 25,
        'wrong_code' => 10,
        'expired_stamp' => 0,
    ];

    /** @var array<string, int> */
    private const array LOGIN_WEIGHTS = [
        'too_fast' => 15,
    ];

    public function __construct(
        CacheItemPoolInterface $securityCachePool,
        LoggerInterface $logger,
        private readonly SecurityMeasureLogRepository $logRepo,
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

    public function weightFor(string $reason, ?string $context): int
    {
        if ($context === self::LOGIN_CONTEXT && array_key_exists($reason, self::LOGIN_WEIGHTS)) {
            return self::LOGIN_WEIGHTS[$reason];
        }

        return self::FORM_WEIGHTS[$reason] ?? 0;
    }

    #[Override]
    protected function buildReport(int $threatLevel, string $summary, array $details = []): ProviderReport
    {
        return new ProviderReport(
            providerKey: $this->getKey(),
            threatLevel: $threatLevel,
            summary: $summary,
            recommendation: $threatLevel >= 100 ? SecurityRecommendation::BlockSession : SecurityRecommendation::Handled,
            details: $details,
        );
    }

    #[Override]
    protected function handlesType(SecurityEventType $type): bool
    {
        return $type === SecurityEventType::FormMeasure;
    }

    #[Override]
    protected function processEvent(SecurityEventType $type, Request $request, array $context, string $ip, array $state): array
    {
        $formContext = is_string($context['context'] ?? null) ? $context['context'] : null;
        $reasons = is_array($context['reasons'] ?? null) ? array_unique(array_map(strval(...), $context['reasons'])) : [];

        $score = (int) ($state['score'] ?? 0);
        $byReason = is_array($state['byReason'] ?? null) ? $state['byReason'] : [];
        foreach ($reasons as $reason) {
            $score += $this->weightFor($reason, $formContext);
            $byReason[$reason] = (int) ($byReason[$reason] ?? 0) + 1;
        }
        $submissions = (int) ($state['submissions'] ?? 0) + 1;

        return [
            'state' => [
                'score' => $score,
                'byReason' => $byReason,
                'submissions' => $submissions,
                'lastSeenAt' => time(),
            ],
            'threatLevel' => min(100, $score),
            'summary' => sprintf('%d blocked form submissions, score %d (last: %s)', $submissions, $score, $formContext ?? 'unknown'),
            'details' => [
                'score' => $score,
                'submissions' => $submissions,
                'byReason' => $byReason,
                'lastForm' => $formContext,
            ],
        ];
    }

    #[Override]
    protected function scanLogs(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $rows = $this->logRepo->findBlocksBetween($from, $to, self::SCAN_LIMIT);

        $score = 0;
        $byReason = [];
        foreach ($rows as $row) {
            $reason = (string) ($row->getDetail()['reason'] ?? $row->getMeasure()->value);
            $score += $this->weightFor($reason, $row->getContext());
            $byReason[$reason] = ($byReason[$reason] ?? 0) + 1;
        }

        return [
            'threatLevel' => min(100, $score),
            'summary' => sprintf('%d blocked form measures, weighted score %d', count($rows), $score),
            'details' => [
                'rows' => count($rows),
                'byReason' => $byReason,
            ],
        ];
    }
}
