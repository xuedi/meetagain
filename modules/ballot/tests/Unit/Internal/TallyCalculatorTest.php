<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Unit\Internal;

use Module\Ballot\Internal\TallyCalculator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TallyCalculatorTest extends TestCase
{
    /**
     * @param list<string>       $optionKeys
     * @param array<string, int> $counts
     * @param list<string>       $expectedTie
     */
    #[DataProvider('provideCounts')]
    public function testTheArithmeticDecidesOrRefusesToDecide(array $optionKeys, array $counts, ?string $expectedWinner, array $expectedTie): void
    {
        // Arrange
        $calculator = new TallyCalculator();

        // Act
        $result = $calculator->decide($optionKeys, $counts);

        // Assert
        self::assertSame($expectedWinner, $result->winningKey);
        self::assertSame($expectedTie, $result->tiedKeys);
    }

    /**
     * @return iterable<string, array{list<string>, array<string, int>, ?string, list<string>}>
     */
    public static function provideCounts(): iterable
    {
        yield 'a clear winner is named' => [['a', 'b'], ['a' => 3, 'b' => 1], 'a', []];
        yield 'two keys level at the top are a tie' => [['a', 'b'], ['a' => 2, 'b' => 2], null, ['a', 'b']];
        yield 'three keys level at the top are all tied' => [['a', 'b', 'c'], ['a' => 1, 'b' => 1, 'c' => 1], null, ['a', 'b', 'c']];
        yield 'a tie below the winner does not matter' => [['a', 'b', 'c'], ['a' => 5, 'b' => 2, 'c' => 2], 'a', []];
        yield 'no votes at all is undecided rather than tied' => [['a', 'b'], [], null, []];
        yield 'every key on zero is undecided rather than tied' => [['a', 'b'], ['a' => 0, 'b' => 0], null, []];
        yield 'a key nobody put on the ballot cannot win' => [['a', 'b'], ['a' => 1, 'forged' => 99], 'a', []];
        yield 'a ballot with no options decides nothing' => [[], ['a' => 3], null, []];
        yield 'one candidate with votes wins outright' => [['a'], ['a' => 1], 'a', []];
        yield 'a candidate keyed by an item id stays a string' => [['20', '21'], ['21' => 2], '21', []];
        yield 'item ids tied at the top stay strings' => [['20', '21'], ['20' => 1, '21' => 1], null, ['20', '21']];
    }
}
