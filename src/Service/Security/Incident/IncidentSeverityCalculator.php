<?php declare(strict_types=1);

namespace App\Service\Security\Incident;

use App\Enum\IncidentSeverity;

final readonly class IncidentSeverityCalculator
{
    public const int WEIGHT_PROBING = 1;
    public const int WEIGHT_ACCESS_DENIED = 2;
    public const int WEIGHT_RATE_LIMIT = 2;

    public const int THRESHOLD_MEDIUM = 5;
    public const int THRESHOLD_HIGH = 15;
    public const int THRESHOLD_CRITICAL = 40;

    public function calculate(
        int $probingHits,
        int $accessDeniedHits,
        int $rateLimitHits,
    ): IncidentSeverity {
        $score =
            self::WEIGHT_PROBING * $probingHits
            + self::WEIGHT_ACCESS_DENIED * $accessDeniedHits
            + self::WEIGHT_RATE_LIMIT * $rateLimitHits;

        return match (true) {
            $score >= self::THRESHOLD_CRITICAL => IncidentSeverity::Critical,
            $score >= self::THRESHOLD_HIGH     => IncidentSeverity::High,
            $score >= self::THRESHOLD_MEDIUM   => IncidentSeverity::Medium,
            default                            => IncidentSeverity::Low,
        };
    }
}
