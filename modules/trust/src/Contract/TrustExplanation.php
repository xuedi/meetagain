<?php declare(strict_types=1);

namespace Module\Trust\Contract;

final readonly class TrustExplanation
{
    /**
     * @param list<TrustActionBreakdown> $actions
     */
    public function __construct(
        public string $context,
        public int $userId,
        public int $rootPoints,
        public array $actions,
        public int $basePoints,
        public int $vouchedPoints,
        public int $vouchCount,
        public int $total,
        public TrustBand $band,
        public int $maxScore,
        public int $minimumToParticipate,
    ) {}

    public function meetsMinimum(): bool
    {
        return $this->total >= $this->minimumToParticipate;
    }

    public function isAtCeiling(): bool
    {
        return $this->total >= $this->maxScore;
    }

    /**
     * @return list<TrustActionBreakdown> the actions a member could still earn on, richest first
     */
    public function unearnedActions(): array
    {
        $open = array_values(array_filter(
            $this->actions,
            static fn(TrustActionBreakdown $action): bool => $action->pointsPerUnit > 0 && !$action->isCapped(),
        ));
        usort($open, static fn(TrustActionBreakdown $a, TrustActionBreakdown $b): int => $b->pointsPerUnit <=> $a->pointsPerUnit);

        return $open;
    }
}
