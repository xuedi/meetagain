<?php declare(strict_types=1);

namespace Module\Trust\Tests\Unit\Contract;

use Module\Trust\Contract\TrustActionBreakdown;
use Module\Trust\Contract\TrustBand;
use Module\Trust\Contract\TrustExplanation;
use PHPUnit\Framework\TestCase;

final class TrustExplanationTest extends TestCase
{
    public function testTheMinimumAndTheCeilingAreReachedAtTheirValue(): void
    {
        // Arrange
        $below = $this->explanation(total: 99);
        $atBoth = $this->explanation(total: 100, maxScore: 100);

        // Act
        $flags = [$below->meetsMinimum(), $below->isAtCeiling(), $atBoth->meetsMinimum(), $atBoth->isAtCeiling()];

        // Assert
        static::assertSame([false, false, true, true], $flags);
    }

    public function testUnearnedActionsLeaveOutCappedAndPointlessOnesAndPutTheRichestFirst(): void
    {
        // Arrange
        $explanation = $this->explanation(actions: [
            $this->action('small', pointsPerUnit: 1),
            $this->action('capped', pointsPerUnit: 9, quantity: 5, cap: 4),
            $this->action('pointless', pointsPerUnit: 0),
            $this->action('rich', pointsPerUnit: 5),
        ]);

        // Act
        $keys = array_map(static fn(TrustActionBreakdown $action): string => $action->key, $explanation->unearnedActions());

        // Assert
        static::assertSame(['rich', 'small'], $keys);
    }

    public function testAnActionIsCappedOnlyOnceItsQuantityPassesTheCap(): void
    {
        // Arrange
        $atCap = $this->action('a', pointsPerUnit: 1, quantity: 4, cap: 4);
        $overCap = $this->action('b', pointsPerUnit: 1, quantity: 5, cap: 4);
        $uncapped = $this->action('c', pointsPerUnit: 1, quantity: 500);

        // Act
        $capped = [$atCap->isCapped(), $overCap->isCapped(), $uncapped->isCapped()];

        // Assert
        static::assertSame([false, true, false], $capped);
    }

    /** @param list<TrustActionBreakdown> $actions */
    private function explanation(int $total = 0, int $maxScore = 1000, array $actions = []): TrustExplanation
    {
        return new TrustExplanation('ctx', 1, 0, $actions, $total, 0, 0, $total, TrustBand::Newcomer, $maxScore, 100);
    }

    private function action(string $key, int $pointsPerUnit, int $quantity = 1, ?int $cap = null): TrustActionBreakdown
    {
        return new TrustActionBreakdown($key, $key, $quantity, $quantity, $cap, $pointsPerUnit, $quantity * $pointsPerUnit);
    }
}
