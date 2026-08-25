<?php declare(strict_types=1);

namespace Module\Trust\Contract;

use InvalidArgumentException;

final readonly class TrustConfig
{
    /**
     * @param array<string, int>   $pointsPerAction points awarded per unit of an action, overriding its descriptor
     * @param array<string, int>   $capsPerAction   most units of an action counted per member, overriding its descriptor
     * @param array{int, int, int} $bandThresholds
     */
    public function __construct(
        public int $maxScore = 1000,
        public int $percentSlight = 10,
        public int $percentTrusted = 25,
        public int $percentAbsolute = 50,
        public int $rootPointsPrimary = 1000,
        public int $rootPointsSecondary = 500,
        public array $pointsPerAction = [],
        public array $capsPerAction = [],
        public int $minimumToParticipate = 0,
        public array $bandThresholds = [50, 200, 500],
    ) {
        $this->assertRange('maxScore', $maxScore, 1, 1_000_000);
        $this->assertRange('percentSlight', $percentSlight, 0, 100);
        $this->assertRange('percentTrusted', $percentTrusted, 0, 100);
        $this->assertRange('percentAbsolute', $percentAbsolute, 0, 100);
        $this->assertRange('rootPointsPrimary', $rootPointsPrimary, 0, $maxScore);
        $this->assertRange('rootPointsSecondary', $rootPointsSecondary, 0, $maxScore);
        $this->assertRange('minimumToParticipate', $minimumToParticipate, 0, $maxScore);

        foreach ($pointsPerAction as $key => $points) {
            $this->assertActionKey($key);
            $this->assertRange('pointsPerAction[' . $key . ']', $points, 0, $maxScore);
        }
        foreach ($capsPerAction as $key => $cap) {
            $this->assertActionKey($key);
            $this->assertRange('capsPerAction[' . $key . ']', $cap, 0, 1_000_000);
        }

        if (count($bandThresholds) !== 3) {
            throw new InvalidArgumentException('bandThresholds must hold exactly three values.');
        }
        $sorted = $bandThresholds;
        sort($sorted);
        if ($sorted !== $bandThresholds) {
            throw new InvalidArgumentException('bandThresholds must be in ascending order.');
        }
        foreach ($bandThresholds as $threshold) {
            $this->assertRange('bandThresholds', $threshold, 0, $maxScore);
        }
    }

    public function percentFor(TrustLevel $level): int
    {
        return match ($level) {
            TrustLevel::Slight => $this->percentSlight,
            TrustLevel::Trusted => $this->percentTrusted,
            TrustLevel::Absolute => $this->percentAbsolute,
        };
    }

    public function pointsFor(ActionDescriptor $descriptor): int
    {
        return $this->pointsPerAction[$descriptor->key] ?? $descriptor->defaultPoints;
    }

    public function capFor(ActionDescriptor $descriptor): ?int
    {
        return $this->capsPerAction[$descriptor->key] ?? $descriptor->quantityCap;
    }

    public function bandFor(int $score): TrustBand
    {
        [$known, $trusted, $highly] = $this->bandThresholds;

        return match (true) {
            $score >= $highly => TrustBand::Highly,
            $score >= $trusted => TrustBand::Trusted,
            $score >= $known => TrustBand::Known,
            default => TrustBand::Newcomer,
        };
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'maxScore' => $this->maxScore,
            'percentSlight' => $this->percentSlight,
            'percentTrusted' => $this->percentTrusted,
            'percentAbsolute' => $this->percentAbsolute,
            'rootPointsPrimary' => $this->rootPointsPrimary,
            'rootPointsSecondary' => $this->rootPointsSecondary,
            'pointsPerAction' => $this->pointsPerAction,
            'capsPerAction' => $this->capsPerAction,
            'minimumToParticipate' => $this->minimumToParticipate,
            'bandThresholds' => array_values($this->bandThresholds),
        ];
    }

    private function assertActionKey(mixed $key): void
    {
        if (!is_string($key) || $key === '') {
            throw new InvalidArgumentException('An action key must be a non-empty string.');
        }
    }

    private function assertRange(string $name, int $value, int $min, int $max): void
    {
        if ($value < $min || $value > $max) {
            throw new InvalidArgumentException(sprintf('%s must be between %d and %d, got %d.', $name, $min, $max, $value));
        }
    }
}
