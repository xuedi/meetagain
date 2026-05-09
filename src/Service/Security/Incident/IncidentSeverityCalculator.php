<?php declare(strict_types=1);

namespace App\Service\Security\Incident;

use App\Enum\IncidentSeverity;

/**
 * Brute-force gets the highest weight because it is a credential-attack signal;
 * a single brute-force hit alone never crosses medium, but combined with other
 * sources it dominates the score quickly.
 */
final readonly class IncidentSeverityCalculator
{
    public const int WEIGHT_PROBING = 1;
    public const int WEIGHT_ACCESS_DENIED = 2;
    public const int WEIGHT_RATE_LIMIT = 2;
    public const int WEIGHT_BRUTE_FORCE = 5;

    public const int THRESHOLD_MEDIUM = 5;
    public const int THRESHOLD_HIGH = 15;
    public const int THRESHOLD_CRITICAL = 40;

    public function calculate(
        int $probingHits,
        int $accessDeniedHits,
        int $rateLimitHits,
        int $bruteForceHits,
    ): IncidentSeverity {
        $score =
            self::WEIGHT_PROBING * $probingHits
            + self::WEIGHT_ACCESS_DENIED * $accessDeniedHits
            + self::WEIGHT_RATE_LIMIT * $rateLimitHits
            + self::WEIGHT_BRUTE_FORCE * $bruteForceHits;

        return match (true) {
            $score >= self::THRESHOLD_CRITICAL => IncidentSeverity::Critical,
            $score >= self::THRESHOLD_HIGH     => IncidentSeverity::High,
            $score >= self::THRESHOLD_MEDIUM   => IncidentSeverity::Medium,
            default                            => IncidentSeverity::Low,
        };
    }
}
