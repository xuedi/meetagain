<?php declare(strict_types=1);

namespace Module\Trust\Tests\Unit;

use Module\Trust\Contract\TrustConfig;
use Module\Trust\Contract\TrustLevel;
use Module\Trust\Internal\ScoreCalculator;
use PHPUnit\Framework\TestCase;
use Psr\Log\NullLogger;

class ScoreCalculatorTest extends TestCase
{
    public function testChainWatersDownAtEachHop(): void
    {
        // Arrange
        $calculator = new ScoreCalculator(new NullLogger());
        $edges = [
            ['from' => 1, 'to' => 2, 'level' => TrustLevel::Absolute],
            ['from' => 2, 'to' => 3, 'level' => TrustLevel::Trusted],
            ['from' => 3, 'to' => 4, 'level' => TrustLevel::Slight],
        ];

        // Act
        $scores = $calculator->compute([1 => 1000], $edges, new TrustConfig());

        // Assert
        self::assertSame(1000, $scores[1]);
        self::assertSame(500, $scores[2]);
        self::assertSame(125, $scores[3]);
        self::assertSame(12, $scores[4]);
    }

    public function testTwoNodeCycleConvergesBelowTheCap(): void
    {
        // Arrange
        $calculator = new ScoreCalculator(new NullLogger());
        $edges = [
            ['from' => 1, 'to' => 2, 'level' => TrustLevel::Absolute],
            ['from' => 2, 'to' => 1, 'level' => TrustLevel::Absolute],
        ];

        // Act
        $scores = $calculator->compute([1 => 100, 2 => 100], $edges, new TrustConfig());

        // Assert
        self::assertLessThanOrEqual(1000, $scores[1]);
        self::assertLessThanOrEqual(1000, $scores[2]);
        self::assertGreaterThan(100, $scores[1]);
        self::assertSame($scores[1], $scores[2]);
    }

    public function testThreeNodeCycleIsIndependentOfEdgeOrder(): void
    {
        // Arrange
        $calculator = new ScoreCalculator(new NullLogger());
        $edges = [
            ['from' => 1, 'to' => 2, 'level' => TrustLevel::Trusted],
            ['from' => 2, 'to' => 3, 'level' => TrustLevel::Trusted],
            ['from' => 3, 'to' => 1, 'level' => TrustLevel::Trusted],
        ];
        $base = [1 => 400];

        // Act
        $forward = $calculator->compute($base, $edges, new TrustConfig());
        $reversed = $calculator->compute($base, array_reverse($edges), new TrustConfig());

        // Assert
        ksort($forward);
        ksort($reversed);
        self::assertSame($forward, $reversed);
    }

    public function testManyStrongEdgesClampToTheCap(): void
    {
        // Arrange
        $calculator = new ScoreCalculator(new NullLogger());
        $base = [];
        $edges = [];
        for ($voucher = 1; $voucher <= 10; $voucher++) {
            $base[$voucher] = 1000;
            $edges[] = ['from' => $voucher, 'to' => 99, 'level' => TrustLevel::Absolute];
        }

        // Act
        $scores = $calculator->compute($base, $edges, new TrustConfig());

        // Assert
        self::assertSame(1000, $scores[99]);
    }

    public function testSelfEdgeIsDropped(): void
    {
        // Arrange
        $calculator = new ScoreCalculator(new NullLogger());
        $edges = [['from' => 1, 'to' => 1, 'level' => TrustLevel::Absolute]];

        // Act
        $scores = $calculator->compute([1 => 200], $edges, new TrustConfig());

        // Assert
        self::assertSame(200, $scores[1]);
    }

    public function testUserWithNothingScoresZero(): void
    {
        // Arrange
        $calculator = new ScoreCalculator(new NullLogger());
        $edges = [['from' => 1, 'to' => 2, 'level' => TrustLevel::Slight]];

        // Act
        $scores = $calculator->compute([1 => 0, 3 => 0], $edges, new TrustConfig());

        // Assert
        self::assertSame(0, $scores[2]);
        self::assertSame(0, $scores[3]);
    }

    public function testActionPointsAloneAreTheWholeScore(): void
    {
        // Arrange
        $calculator = new ScoreCalculator(new NullLogger());

        // Act
        $scores = $calculator->compute([7 => 35], [], new TrustConfig());

        // Assert
        self::assertSame(35, $scores[7]);
    }

    public function testRevokingAnEdgeLowersEveryNodeBehindIt(): void
    {
        // Arrange
        $calculator = new ScoreCalculator(new NullLogger());
        $edges = [
            ['from' => 1, 'to' => 2, 'level' => TrustLevel::Absolute],
            ['from' => 2, 'to' => 3, 'level' => TrustLevel::Absolute],
        ];

        // Act
        $before = $calculator->compute([1 => 1000], $edges, new TrustConfig());
        $after = $calculator->compute([1 => 1000], [$edges[1]], new TrustConfig());

        // Assert
        self::assertGreaterThan($after[2], $before[2]);
        self::assertGreaterThan($after[3], $before[3]);
        self::assertSame(0, $after[3]);
    }

    public function testAnEmptyGraphYieldsAnEmptyMap(): void
    {
        // Arrange
        $calculator = new ScoreCalculator(new NullLogger());

        // Act
        $scores = $calculator->compute([], [], new TrustConfig());

        // Assert
        self::assertSame([], $scores);
    }
}
