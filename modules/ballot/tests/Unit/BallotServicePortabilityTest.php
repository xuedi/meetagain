<?php declare(strict_types=1);

namespace Module\Ballot\Tests\Unit;

use App\Entity\User;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Module\Ballot\Contract\BallotStatus;
use Module\Ballot\Contract\BallotSubject;
use Module\Ballot\Contract\Candidate;
use Module\Ballot\Contract\PortableBallot;
use Module\Ballot\Contract\SettlementListenerInterface;
use Module\Ballot\Contract\SettlementMode;
use Module\Ballot\Contract\TallyMode;
use Module\Ballot\Internal\BallotService;
use Module\Ballot\Internal\ElectorateRegistry;
use Module\Ballot\Internal\Entity\Ballot;
use Module\Ballot\Internal\Entity\BallotOption;
use Module\Ballot\Internal\Entity\BallotVote;
use Module\Ballot\Internal\Repository\BallotRepository;
use Module\Ballot\Internal\Repository\BallotVoteRepository;
use Module\Ballot\Internal\SettlementRegistry;
use Module\Ballot\Internal\TallyCalculator;
use Module\Ballot\Internal\VisibilityFilterService;
use PHPUnit\Framework\TestCase;
use ReflectionProperty;

class BallotServicePortabilityTest extends TestCase
{
    /** @var list<object> */
    private array $persisted = [];

    public function testTheExportCarriesEveryBallotWithItsOptionsOutcomeAndVotes(): void
    {
        // Arrange
        $ballot = new Ballot(
            'event.item.film',
            new DateTimeImmutable('2030-01-10 18:00'),
            3,
            TallyMode::Single,
            SettlementMode::Confirmed,
            new DateTimeImmutable('2030-01-01 12:00'),
            'event',
            9,
            'Film night',
        );
        new ReflectionProperty(Ballot::class, 'id')->setValue($ballot, 5);
        $ballot->addOption(new BallotOption($ballot, '12', 'Alien', 0));
        $ballot->addOption(new BallotOption($ballot, '14', 'Brazil', 1));
        $ballot->recordSettlement('14', 3, new DateTimeImmutable('2030-01-11 09:00'));

        // Act
        $exported = $this->service([$ballot], [5 => [7 => ['14'], 8 => ['12']]])->exportAll();

        // Assert
        static::assertEquals(
            [new PortableBallot(
                purpose: 'event.item.film',
                status: BallotStatus::Settled,
                tallyMode: TallyMode::Single,
                settlementMode: SettlementMode::Confirmed,
                deadline: new DateTimeImmutable('2030-01-10 18:00'),
                openedByUserId: 3,
                createdAt: new DateTimeImmutable('2030-01-01 12:00'),
                candidates: [new Candidate('12', 'Alien'), new Candidate('14', 'Brazil')],
                votes: [7 => ['14'], 8 => ['12']],
                title: 'Film night',
                subject: new BallotSubject('event', 9),
                winningKey: '14',
                settledByUserId: 3,
                settledAt: new DateTimeImmutable('2030-01-11 09:00'),
            )],
            $exported,
        );
    }

    public function testRestoreWritesTheBallotAsGivenWithoutTallyingOrNotifying(): void
    {
        // Arrange
        $listener = $this->createMock(SettlementListenerInterface::class);
        $listener->expects($this->never())->method('settled');
        $portable = new PortableBallot(
            purpose: 'event.item.film',
            status: BallotStatus::Tallied,
            tallyMode: TallyMode::Approval,
            settlementMode: SettlementMode::Automatic,
            deadline: new DateTimeImmutable('2030-01-10 18:00'),
            openedByUserId: 3,
            createdAt: new DateTimeImmutable('2030-01-01 12:00'),
            candidates: [new Candidate('12', 'Alien'), new Candidate('14', 'Brazil'), new Candidate('12', 'Alien again')],
            votes: [7 => ['12', '99'], 8 => ['14']],
            subject: new BallotSubject('event', 90),
            tiedKeys: ['12', '14'],
        );

        // Act
        $this->service(listeners: [$listener])->restore($portable);

        // Assert
        $ballot = $this->persistedOf(Ballot::class)[0];
        static::assertSame(BallotStatus::Tallied, $ballot->getStatus());
        static::assertNull($ballot->getWinningKey());
        static::assertSame(['12', '14'], $ballot->getTiedKeys());
        static::assertSame(['12', '14'], $ballot->getOptionKeys());
        static::assertSame('event', $ballot->getSubjectType());
        static::assertSame(90, $ballot->getSubjectId());
        static::assertSame('2030-01-01 12:00', $ballot->getCreatedAt()->format('Y-m-d H:i'));
        static::assertSame(
            [[7, '12'], [8, '14']],
            array_map(static fn(BallotVote $vote): array => [$vote->getUser()->getId(), $vote->getOptionKey()], $this->persistedOf(BallotVote::class)),
        );
    }

    /**
     * @template T of object
     * @param class-string<T> $class
     * @return list<T>
     */
    private function persistedOf(string $class): array
    {
        return array_values(array_filter($this->persisted, static fn(object $entity): bool => $entity instanceof $class));
    }

    /**
     * @param list<Ballot> $ballots
     * @param array<int, array<int, list<string>>> $selections
     * @param list<SettlementListenerInterface> $listeners
     */
    private function service(array $ballots = [], array $selections = [], array $listeners = []): BallotService
    {
        $ballotRepository = $this->createStub(BallotRepository::class);
        $ballotRepository->method('findBy')->willReturn($ballots);
        $voteRepository = $this->createStub(BallotVoteRepository::class);
        $voteRepository->method('findAllSelections')->willReturn($selections);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });
        $em->method('getReference')->willReturnCallback(static function (string $class, mixed $id): User {
            $user = new User();
            new ReflectionProperty(User::class, 'id')->setValue($user, $id);

            return $user;
        });

        return new BallotService(
            $ballotRepository,
            $voteRepository,
            $em,
            new TallyCalculator(),
            new ElectorateRegistry([]),
            new VisibilityFilterService([]),
            new SettlementRegistry($listeners),
        );
    }
}
