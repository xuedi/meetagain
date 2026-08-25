<?php declare(strict_types=1);

namespace Module\Trust\Internal;

use Module\Trust\Contract\TrustConfig;
use Module\Trust\Contract\TrustLevel;
use Psr\Log\LoggerInterface;

final readonly class ScoreCalculator
{
    private const int MAX_GRAPH_USERS = 10000;
    private const int MAX_ROUNDS = 50;

    public function __construct(
        private LoggerInterface $logger,
    ) {}

    /**
     * @param array<int, int>                                  $basePoints  user id => root plus action points
     * @param list<array{from: int, to: int, level: TrustLevel}> $edges
     * @return array<int, int>
     */
    public function compute(array $basePoints, array $edges, TrustConfig $config): array
    {
        $base = [];
        foreach ($basePoints as $userId => $points) {
            $base[$userId] = min($config->maxScore, max(0, $points));
        }

        $accepted = [];
        foreach ($edges as $edge) {
            if ($edge['from'] === $edge['to']) {
                continue;
            }
            $base[$edge['from']] ??= 0;
            $base[$edge['to']] ??= 0;
            $accepted[] = $edge;
        }

        if ($base === []) {
            return [];
        }

        if (count($base) > self::MAX_GRAPH_USERS) {
            $this->logger->warning('Trust graph exceeds the supported size, computation may be slow', [
                'users' => count($base),
                'limit' => self::MAX_GRAPH_USERS,
            ]);
        }

        $scores = array_map(static fn(int $points): float => (float) $points, $base);

        for ($round = 0; $round < self::MAX_ROUNDS; $round++) {
            $next = array_map(static fn(int $points): float => (float) $points, $base);
            foreach ($accepted as $edge) {
                $next[$edge['to']] += $scores[$edge['from']] * $config->percentFor($edge['level']) / 100;
            }

            $delta = 0.0;
            foreach ($next as $userId => $value) {
                $capped = min((float) $config->maxScore, $value);
                $delta = max($delta, abs($capped - $scores[$userId]));
                $next[$userId] = $capped;
            }

            $scores = $next;
            if ($delta < 1.0) {
                break;
            }
        }

        return array_map(static fn(float $value): int => (int) floor($value), $scores);
    }
}
