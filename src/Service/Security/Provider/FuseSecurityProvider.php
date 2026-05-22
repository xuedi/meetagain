<?php declare(strict_types=1);

namespace App\Service\Security\Provider;

use App\Enum\SecurityEventType;
use App\Enum\SecurityRecommendation;
use App\Service\Security\ProviderReport;
use DateTimeImmutable;
use Override;
use Symfony\Component\HttpFoundation\Request;

final class FuseSecurityProvider extends AbstractSecurityProvider
{
    public const string KEY = 'fuse';
    public const int EVENTS_PER_IP_FUSE = 100;
    public const int FUSE_WINDOW_SECONDS = 60;

    #[Override]
    public function getKey(): string
    {
        return self::KEY;
    }

    #[Override]
    public function getPriority(): int
    {
        return 1000;
    }

    #[Override]
    protected function handlesType(SecurityEventType $type): bool
    {
        return true;
    }

    #[Override]
    protected function resolveStateKey(string $sessionId, string $ip): string
    {
        return $ip !== '' ? $ip : 'unknown';
    }

    #[Override]
    protected function processEvent(
        SecurityEventType $type,
        Request $request,
        array $context,
        string $ip,
        array $state,
    ): array {
        $now = time();
        $windowStartedAt = (int) ($state['windowStartedAt'] ?? 0);
        $count = (int) ($state['count'] ?? 0);

        if ($windowStartedAt === 0 || ($now - $windowStartedAt) > self::FUSE_WINDOW_SECONDS) {
            $windowStartedAt = $now;
            $count = 0;
        }

        ++$count;
        $elapsed = $now - $windowStartedAt;

        if ($count > self::EVENTS_PER_IP_FUSE) {
            $threatLevel = 100;
            $summary = sprintf('IP rate fuse tripped: %d events in %ds', $count, max(1, $elapsed));
            $details = [
                'ip' => $ip,
                'count' => $count,
                'windowStartedAt' => $windowStartedAt,
                'eventsPerWindow' => self::EVENTS_PER_IP_FUSE,
                'windowSeconds' => self::FUSE_WINDOW_SECONDS,
            ];
        } else {
            $threatLevel = (int) min(99, ($count / self::EVENTS_PER_IP_FUSE) * 100);
            $summary = sprintf('%d/%d events in current window', $count, self::EVENTS_PER_IP_FUSE);
            $details = [
                'ip' => $ip,
                'count' => $count,
                'windowStartedAt' => $windowStartedAt,
                'eventsPerWindow' => self::EVENTS_PER_IP_FUSE,
                'windowSeconds' => self::FUSE_WINDOW_SECONDS,
            ];
        }

        return [
            'state' => [
                'windowStartedAt' => $windowStartedAt,
                'count' => $count,
            ],
            'threatLevel' => $threatLevel,
            'summary' => $summary,
            'details' => $details,
        ];
    }

    #[Override]
    protected function buildReport(int $threatLevel, string $summary, array $details = []): ProviderReport
    {
        $recommendation = $threatLevel >= 100
            ? SecurityRecommendation::BlockShortCircuit
            : SecurityRecommendation::Handled;

        return new ProviderReport(
            providerKey: $this->getKey(),
            threatLevel: $threatLevel,
            summary: $summary,
            recommendation: $recommendation,
            details: $details,
        );
    }

    #[Override]
    protected function scanLogs(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        return [
            'threatLevel' => 0,
            'summary' => 'fuse has no retrospective view',
            'details' => [],
        ];
    }
}
