<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotRequest;
use Module\Ballot\Contract\Candidate;
use Module\Ballot\Tests\Stub\BlindfoldVisibilityFilter;
use Module\Ballot\Tests\Stub\RecordingSettlementListener;
use Module\Ballot\Tests\Stub\SingleMemberElectorate;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Module\Members;

final class SeamsTest extends KernelTestCase
{
    public function testAClaimedPurposeReachesItsSettlementListener(): void
    {
        // Arrange
        self::bootKernel();
        $ballots = self::getContainer()->get(BallotInterface::class);
        $listener = self::getContainer()->get(RecordingSettlementListener::class);
        $voter = $this->voterIds()[0];
        $id = $ballots->open($this->request(RecordingSettlementListener::PURPOSE, $voter));

        // Act
        $ballots->settle($id, 'a', $voter);

        // Assert
        self::assertCount(1, $listener->settled);
        self::assertSame('a', $listener->settled[0]->winningKey);
        self::assertSame($id, $listener->settled[0]->ballotId);
    }

    public function testAnUnclaimedPurposeSettlesAndWritesNothing(): void
    {
        // Arrange
        self::bootKernel();
        $ballots = self::getContainer()->get(BallotInterface::class);
        $listener = self::getContainer()->get(RecordingSettlementListener::class);
        $voter = $this->voterIds()[0];
        $id = $ballots->open($this->request('test.nobody_claims_this', $voter));

        // Act
        $outcome = $ballots->settle($id, 'b', $voter);

        // Assert
        self::assertSame('b', $outcome->winningKey);
        self::assertSame([], $listener->settled);
    }

    public function testAnElectorateProviderNarrowsWhoMayVote(): void
    {
        // Arrange
        self::bootKernel();
        $ballots = self::getContainer()->get(BallotInterface::class);
        $electorate = self::getContainer()->get(SingleMemberElectorate::class);
        [$allowed, $refused] = $this->voterIds();
        $electorate->allowedUserId = $allowed;
        $id = $ballots->open($this->request(SingleMemberElectorate::PURPOSE, $allowed));

        // Act
        $mayVote = [$ballots->mayVote($id, $allowed), $ballots->mayVote($id, $refused)];

        // Assert
        self::assertSame([true, false], $mayVote);
        $this->expectException(DomainException::class);
        $ballots->cast($id, $refused, ['a']);
    }

    public function testWithNoProviderEveryAuthenticatedMemberMayVote(): void
    {
        // Arrange
        self::bootKernel();
        $ballots = self::getContainer()->get(BallotInterface::class);
        [$first, $second] = $this->voterIds();
        $id = $ballots->open($this->request('test.open_to_all', $first));

        // Act
        $verdicts = [$ballots->mayVote($id, $first), $ballots->mayVote($id, $second), $ballots->mayVote($id, 0)];

        // Assert
        self::assertSame([true, true, false], $verdicts);
    }

    public function testAVisibilityFilterHidesTheBallotEntirely(): void
    {
        // Arrange
        self::bootKernel();
        $ballots = self::getContainer()->get(BallotInterface::class);
        $filter = self::getContainer()->get(BlindfoldVisibilityFilter::class);
        $voter = $this->voterIds()[0];
        $id = $ballots->open($this->request('test.hidden', $voter));
        self::assertNotNull($ballots->view($id, $voter), 'visible before the filter has an opinion');

        // Act
        $filter->hiddenBallotIds = [$id];

        // Assert
        self::assertNull($ballots->view($id, $voter));
        self::assertFalse($ballots->mayVote($id, $voter));
        self::assertSame([], array_filter($ballots->listOpenFor($voter), static fn(object $view): bool => $view->id === $id));
    }

    private function request(string $purpose, int $openedBy): BallotRequest
    {
        return new BallotRequest($purpose, [new Candidate('a', 'Alpha'), new Candidate('b', 'Beta')], new DateTimeImmutable('+7 days'), $openedBy);
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
