<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Functional;

use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\EntityManagerInterface;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotRequest;
use Module\Ballot\Contract\BallotStatus;
use Module\Ballot\Contract\Candidate;
use Module\Ballot\Contract\SettlementMode;
use Module\Ballot\Internal\Cron\SettleDueBallotsCron;
use Module\Ballot\Internal\Notification\OpenBallotNotificationProvider;
use Module\Ballot\Tests\Stub\RecordingSettlementListener;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Output\BufferedOutput;
use Tests\Module\Members;

final class DeadlineTest extends KernelTestCase
{
    private const string BALLOT_ROUTE = 'app_ballot_index';

    public function testAClearWinnerIsSettledAutomaticallyOnTheDeadline(): void
    {
        // Arrange
        self::bootKernel();
        $ballots = self::getContainer()->get(BallotInterface::class);
        self::assertInstanceOf(BallotInterface::class, $ballots);
        $listener = self::getContainer()->get(RecordingSettlementListener::class);
        [$first, $second] = $this->voterIds();
        $id = $this->overdueBallot(RecordingSettlementListener::PURPOSE, SettlementMode::Automatic, [$first => ['b'], $second => ['b']]);

        // Act
        $result = $this->runCron();

        // Assert
        self::assertStringContainsString('1 settled', $result);
        self::assertSame(BallotStatus::Settled, $ballots->view($id, $first)?->status);
        self::assertCount(1, $listener->settled);
        self::assertSame('b', $listener->settled[0]->winningKey);
    }

    public function testATieIsTalliedButLeftForAPerson(): void
    {
        // Arrange
        self::bootKernel();
        $ballots = self::getContainer()->get(BallotInterface::class);
        self::assertInstanceOf(BallotInterface::class, $ballots);
        [$first, $second] = $this->voterIds();
        $id = $this->overdueBallot('test.tie', SettlementMode::Automatic, [$first => ['a'], $second => ['b']]);

        // Act
        $result = $this->runCron();

        // Assert
        self::assertStringContainsString('1 left for a person', $result);
        $view = $ballots->view($id, $first);
        self::assertSame(BallotStatus::Tallied, $view?->status);
        self::assertTrue($view->isTied());
        self::assertSame(['a', 'b'], $view->tiedKeys);
    }

    public function testAConfirmedBallotWaitsForAPersonEvenWithAClearWinner(): void
    {
        // Arrange
        self::bootKernel();
        $ballots = self::getContainer()->get(BallotInterface::class);
        self::assertInstanceOf(BallotInterface::class, $ballots);
        [$first, $second] = $this->voterIds();
        $id = $this->overdueBallot('test.confirmed', SettlementMode::Confirmed, [$first => ['a'], $second => ['a']]);

        // Act
        $this->runCron();

        // Assert
        $view = $ballots->view($id, $first);
        self::assertSame(BallotStatus::Tallied, $view?->status);
        self::assertSame('a', $view->winningKey);
    }

    public function testTheBellStaysSilentUntilAMemberHasSomethingToVoteOn(): void
    {
        // Arrange
        self::bootKernel();
        $bell = self::getContainer()->get(OpenBallotNotificationProvider::class);
        $ballots = self::getContainer()->get(BallotInterface::class);
        self::assertInstanceOf(BallotInterface::class, $ballots);
        $member = new Members($this->em())->member('Voter');
        $before = $bell->getNotifications($member);

        // Act
        $ballots->open($this->request('test.bell', SettlementMode::Automatic, (int) $member->getId()));

        // Assert
        $after = $bell->getNotifications($member);
        self::assertSame([], $before);
        self::assertCount(1, $after);
        self::assertSame(self::BALLOT_ROUTE, $after[0]->route);
    }

    /**
     * @param array<int, list<string>> $votes
     */
    private function overdueBallot(string $purpose, SettlementMode $mode, array $votes): int
    {
        $ballots = self::getContainer()->get(BallotInterface::class);
        self::assertInstanceOf(BallotInterface::class, $ballots);
        $id = $ballots->open($this->request($purpose, $mode, (int) array_key_first($votes)));

        foreach ($votes as $userId => $keys) {
            $ballots->cast($id, $userId, $keys);
        }

        self::getContainer()
            ->get(Connection::class)
            ->update('mod_ballot', ['deadline' => new DateTimeImmutable('-1 hour')], ['id' => $id], ['deadline' => Types::DATETIME_IMMUTABLE]);
        $this->em()->clear();

        return $id;
    }

    private function request(string $purpose, SettlementMode $mode, int $openedBy): BallotRequest
    {
        return new BallotRequest(
            $purpose,
            [new Candidate('a', 'Alpha'), new Candidate('b', 'Beta')],
            new DateTimeImmutable('+7 days'),
            $openedBy,
            null,
            settlementMode: $mode,
        );
    }

    private function runCron(): string
    {
        $output = new BufferedOutput();
        $result = self::getContainer()->get(SettleDueBallotsCron::class)->runCronTask($output);

        return $result->message . "\n" . $output->fetch();
    }

    /**
     * @return list<int>
     */
    private function voterIds(): array
    {
        $members = new Members(self::getContainer()->get(EntityManagerInterface::class));

        return [(int) $members->member('First')->getId(), (int) $members->member('Second')->getId()];
    }

    private function em(): EntityManagerInterface
    {
        return self::getContainer()->get(EntityManagerInterface::class);
    }
}
