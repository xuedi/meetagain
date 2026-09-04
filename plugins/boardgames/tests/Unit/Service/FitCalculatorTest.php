<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Unit\Service;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Enum\PlayerFit;
use Plugin\Boardgames\Service\FitCalculator;

class FitCalculatorTest extends TestCase
{
    #[DataProvider('provideHeadcounts')]
    public function testFitAgainstHeadcount(?int $min, ?int $max, int $headcount, PlayerFit $expected): void
    {
        // Arrange
        $game = new Game();
        $game->setName('Catan');
        $game->setMinPlayers($min);
        $game->setMaxPlayers($max);

        // Act
        $fit = new FitCalculator()->forGame($game, $headcount);

        // Assert
        static::assertSame($expected, $fit);
    }

    /**
     * @return iterable<string, array{0: int|null, 1: int|null, 2: int, 3: PlayerFit}>
     */
    public static function provideHeadcounts(): iterable
    {
        yield 'headcount inside the range fits' => [3, 4, 4, PlayerFit::Fits];
        yield 'headcount on the lower bound fits' => [3, 4, 3, PlayerFit::Fits];
        yield 'headcount below the minimum is too few' => [3, 4, 2, PlayerFit::TooFew];
        yield 'headcount above the maximum is too many' => [3, 4, 7, PlayerFit::TooMany];
        yield 'open-ended maximum never overflows' => [2, null, 99, PlayerFit::Fits];
        yield 'open-ended minimum never underflows' => [null, 5, 1, PlayerFit::Fits];
        yield 'a solo game fits a table of one' => [1, 1, 1, PlayerFit::Fits];
        yield 'no player counts at all is unknown' => [null, null, 4, PlayerFit::Unknown];
        yield 'no headcount yet is unknown' => [3, 4, 0, PlayerFit::Unknown];
    }
}
