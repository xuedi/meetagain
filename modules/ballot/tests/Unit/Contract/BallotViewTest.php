<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Unit\Contract;

use DateTimeImmutable;
use Module\Ballot\Contract\BallotStatus;
use Module\Ballot\Contract\BallotView;
use Module\Ballot\Contract\Candidate;
use Module\Ballot\Contract\SettlementMode;
use Module\Ballot\Contract\TallyMode;
use PHPUnit\Framework\TestCase;

final class BallotViewTest extends TestCase
{
    private const string DEADLINE = '2031-01-01 12:00';

    public function testTheWinnerIsTheCandidateBehindTheWinningKey(): void
    {
        // Arrange
        $view = $this->view(winningKey: 'b');

        // Act
        $winner = $view->winner();

        // Assert
        static::assertSame('B', $winner?->label);
        static::assertFalse($view->isTied());
    }

    public function testAKeyWithoutACandidateHasNone(): void
    {
        // Arrange
        $view = $this->view();

        // Act
        $candidate = $view->candidateFor('missing');

        // Assert
        static::assertNull($candidate);
        static::assertNull($view->winner());
    }

    public function testVotesForAnUncountedKeyAreZero(): void
    {
        // Arrange
        $view = $this->view(tally: ['a' => 3]);

        // Act
        $votes = [$view->votesFor('a'), $view->votesFor('b')];

        // Assert
        static::assertSame([3, 0], $votes);
    }

    public function testTwoTiedKeysWithoutAWinnerAreATie(): void
    {
        // Arrange
        $view = $this->view(tiedKeys: ['a', 'b']);

        // Act
        $tied = $view->isTied();

        // Assert
        static::assertTrue($tied);
    }

    public function testABallotIsDueFromItsDeadlineOn(): void
    {
        // Arrange
        $view = $this->view();

        // Act
        $due = [
            $view->isDue(new DateTimeImmutable(self::DEADLINE . ' -1 second')),
            $view->isDue(new DateTimeImmutable(self::DEADLINE)),
        ];

        // Assert
        static::assertSame([false, true], $due);
    }

    public function testTheViewerHasVotedOnceTheyHoldASelection(): void
    {
        // Arrange
        $fresh = $this->view();
        $voted = $this->view(viewerSelection: ['a']);

        // Act
        $hasVoted = [$fresh->hasVoted(), $voted->hasVoted()];

        // Assert
        static::assertSame([false, true], $hasVoted);
    }

    public function testOnlyAnOpenBallotIsOpen(): void
    {
        // Arrange
        $open = $this->view();
        $settled = $this->view(status: BallotStatus::Settled);

        // Act
        $isOpen = [$open->isOpen(), $settled->isOpen()];

        // Assert
        static::assertSame([true, false], $isOpen);
    }

    /**
     * @param array<string, int> $tally
     * @param list<string> $tiedKeys
     * @param list<string> $viewerSelection
     */
    private function view(
        BallotStatus $status = BallotStatus::Open,
        array $tally = [],
        ?string $winningKey = null,
        array $tiedKeys = [],
        array $viewerSelection = [],
    ): BallotView {
        return new BallotView(
            id: 1,
            purpose: 'test.purpose',
            status: $status,
            candidates: [new Candidate('a', 'A'), new Candidate('b', 'B')],
            tally: $tally,
            deadline: new DateTimeImmutable(self::DEADLINE),
            tallyMode: TallyMode::Approval,
            settlementMode: SettlementMode::Automatic,
            openedByUserId: 1,
            voterCount: 2,
            winningKey: $winningKey,
            tiedKeys: $tiedKeys,
            viewerSelection: $viewerSelection,
        );
    }
}
