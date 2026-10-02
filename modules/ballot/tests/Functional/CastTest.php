<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Functional;

use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use DomainException;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotRequest;
use Module\Ballot\Contract\Candidate;
use Module\Ballot\Contract\TallyMode;
use Module\Ballot\Tests\Stub\BlindfoldVisibilityFilter;
use PHPUnit\Framework\Attributes\DataProvider;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Tests\Module\Members;

final class CastTest extends KernelTestCase
{
    /** @return iterable<string, array{string}> */
    public static function closingProvider(): iterable
    {
        yield 'tallied' => ['tally'];
        yield 'abandoned' => ['abandon'];
    }

    #[DataProvider('closingProvider')]
    public function testAClosedBallotRefusesAVoteAndKeepsTheOldOne(string $closing): void
    {
        // Arrange
        $ballots = $this->ballots();
        $voter = $this->voterId('Closed');
        $id = $ballots->open($this->request($voter, TallyMode::Approval));
        $ballots->cast($id, $voter, ['a']);
        $ballots->{$closing}($id);

        // Act
        $refused = $this->refuses(static fn() => $ballots->cast($id, $voter, ['b']));

        // Assert
        self::assertTrue($refused);
        self::assertSame(1, $ballots->view($id, $voter)?->votesFor('a'));
        self::assertSame(0, $ballots->view($id, $voter)?->votesFor('b'));
    }

    public function testAHiddenBallotRefusesAVote(): void
    {
        // Arrange
        $ballots = $this->ballots();
        $voter = $this->voterId('Hidden');
        $id = $ballots->open($this->request($voter, TallyMode::Approval));
        self::getContainer()->get(BlindfoldVisibilityFilter::class)->hiddenBallotIds = [$id];

        // Act
        $refused = $this->refuses(static fn() => $ballots->cast($id, $voter, ['a']));

        // Assert
        self::assertTrue($refused);
        self::getContainer()->get(BlindfoldVisibilityFilter::class)->hiddenBallotIds = [];
        self::assertSame(0, $ballots->view($id, $voter)?->voterCount);
    }

    public function testASingleChoiceBallotRecordsOnlyOneSelection(): void
    {
        // Arrange
        $ballots = $this->ballots();
        $voter = $this->voterId('Greedy');
        $id = $ballots->open($this->request($voter, TallyMode::Single));

        // Act
        $ballots->cast($id, $voter, ['a', 'b', 'a']);

        // Assert
        $view = $ballots->view($id, $voter);
        self::assertSame(1, ($view?->votesFor('a') ?? 0) + ($view?->votesFor('b') ?? 0));
    }

    public function testADuplicatedKeyCountsOnceOnAnApprovalBallot(): void
    {
        // Arrange
        $ballots = $this->ballots();
        $voter = $this->voterId('Repeater');
        $id = $ballots->open($this->request($voter, TallyMode::Approval));

        // Act
        $ballots->cast($id, $voter, ['a', 'a', 'a']);

        // Assert
        self::assertSame(1, $ballots->view($id, $voter)?->votesFor('a'));
    }

    private function refuses(callable $cast): bool
    {
        try {
            $cast();
        } catch (DomainException) {
            return true;
        }

        return false;
    }

    private function request(int $openedBy, TallyMode $mode): BallotRequest
    {
        return new BallotRequest(
            'test.cast',
            [new Candidate('a', 'Alpha'), new Candidate('b', 'Beta')],
            new DateTimeImmutable('+7 days'),
            $openedBy,
            null,
            $mode,
        );
    }

    private function ballots(): BallotInterface
    {
        self::bootKernel();

        return self::getContainer()->get(BallotInterface::class);
    }

    private function voterId(string $name): int
    {
        return (int) new Members(self::getContainer()->get(EntityManagerInterface::class))
            ->member($name)
            ->getId();
    }
}
