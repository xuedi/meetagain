<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotRequest;
use Module\Ballot\Contract\BallotStatus;
use Module\Ballot\Contract\BallotSubject;
use Module\Ballot\Contract\Candidate;
use Module\Ballot\Contract\TallyMode;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Module\Members;

final class BallotEngineTest extends KernelTestCase
{
    public function testABallotIsOpenedVotedOnTalliedAndSettled(): void
    {
        // Arrange
        self::bootKernel();
        $ballots = self::getContainer()->get(BallotInterface::class);
        $voters = $this->voterIds();

        // Act
        $id = $ballots->open(
            new BallotRequest(
                'test.smoke',
                [new Candidate('a', 'Alpha'), new Candidate('b', 'Beta')],
                new DateTimeImmutable('+7 days'),
                $voters[0],
                new BallotSubject('event', 1),
            ),
        );
        $ballots->cast($id, $voters[0], ['b']);
        $ballots->cast($id, $voters[1], ['b', 'a']);
        $ballots->cast($id, $voters[1], ['b']);

        // Assert
        $view = $ballots->view($id, $voters[1]);
        self::assertNotNull($view);
        self::assertSame(2, $view->votesFor('b'), 'both members approved b');
        self::assertSame(0, $view->votesFor('a'), 'recasting replaces rather than accumulates');
        self::assertSame(2, $view->voterCount);
        self::assertSame(['b'], $view->viewerSelection);
        self::assertTrue($view->viewerMayVote);

        $outcome = $ballots->tally($id);
        self::assertSame('b', $outcome->winningKey);
        self::assertSame(BallotStatus::Tallied, $outcome->status);

        $settled = $ballots->settle($id, 'b', $voters[0]);
        self::assertSame(BallotStatus::Settled, $settled->status);
        self::assertSame('event', $settled->subject?->type);
    }

    public function testAForgedKeyIsIntersectedAwayAndATieIsRecorded(): void
    {
        // Arrange
        self::bootKernel();
        $ballots = self::getContainer()->get(BallotInterface::class);
        $voters = $this->voterIds();

        // Act
        $id = $ballots->open(
            new BallotRequest(
                'test.smoke',
                [new Candidate('a', 'Alpha'), new Candidate('b', 'Beta')],
                new DateTimeImmutable('+7 days'),
                $voters[0],
                null,
                TallyMode::Single,
            ),
        );
        $ballots->cast($id, $voters[0], ['a', 'forged']);
        $ballots->cast($id, $voters[1], ['b']);

        // Assert
        $view = $ballots->view($id, null);
        self::assertNotNull($view);
        self::assertSame(['a' => 1, 'b' => 1], $view->tally);

        $outcome = $ballots->tally($id);
        self::assertNull($outcome->winningKey);
        self::assertTrue($outcome->isTied());
        self::assertSame(['a', 'b'], $outcome->tiedKeys);
    }

    /**
     * @return list<int>
     */
    private function voterIds(): array
    {
        $members = new Members(self::getContainer()->get(EntityManagerInterface::class));

        return [(int) $members->member('First')->getId(), (int) $members->member('Second')->getId()];
    }
}
