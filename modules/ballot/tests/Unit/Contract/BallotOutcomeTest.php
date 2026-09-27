<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Unit\Contract;

use Module\Ballot\Contract\BallotOutcome;
use Module\Ballot\Contract\BallotStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BallotOutcomeTest extends TestCase
{
    /**
     * @param list<string> $tiedKeys
     */
    #[DataProvider('provideOutcomes')]
    public function testAnOutcomeIsDecidedByAWinnerAndTiedOnlyWithoutOne(?string $winningKey, array $tiedKeys, bool $decided, bool $tied): void
    {
        // Arrange
        $outcome = new BallotOutcome(1, 'test.purpose', BallotStatus::Tallied, $winningKey, $tiedKeys);

        // Act
        $actual = [$outcome->isDecided(), $outcome->isTied()];

        // Assert
        static::assertSame([$decided, $tied], $actual);
    }

    /**
     * @return iterable<string, array{?string, list<string>, bool, bool}>
     */
    public static function provideOutcomes(): iterable
    {
        yield 'a winner' => ['a', [], true, false];
        yield 'two tied keys' => [null, ['a', 'b'], false, true];
        yield 'one tied key is no tie' => [null, ['a'], false, false];
        yield 'no votes at all' => [null, [], false, false];
    }
}
