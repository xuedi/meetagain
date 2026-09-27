<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Unit\Contract;

use Module\Ballot\Contract\BallotStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

final class BallotStatusTest extends TestCase
{
    #[DataProvider('provideStatuses')]
    public function testOnlyAnOpenBallotTakesVotesAndOnlyAFinishedOneIsResolved(BallotStatus $status, bool $acceptsVotes, bool $resolved): void
    {
        // Act
        $actual = [$status->acceptsVotes(), $status->isResolved()];

        // Assert
        static::assertSame([$acceptsVotes, $resolved], $actual);
    }

    /**
     * @return iterable<string, array{BallotStatus, bool, bool}>
     */
    public static function provideStatuses(): iterable
    {
        yield 'open' => [BallotStatus::Open, true, false];
        yield 'tallied' => [BallotStatus::Tallied, false, false];
        yield 'settled' => [BallotStatus::Settled, false, true];
        yield 'abandoned' => [BallotStatus::Abandoned, false, true];
    }
}
