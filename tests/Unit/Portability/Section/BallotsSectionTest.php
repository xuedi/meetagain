<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\Event;
use App\Entity\User;
use App\Portability\Ballot\Purpose\ItemPurpose;
use App\Portability\DataCategory;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\BallotsSection;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Module\Ballot\Contract\BallotInterface;
use Module\Ballot\Contract\BallotStatus;
use Module\Ballot\Contract\BallotSubject;
use Module\Ballot\Contract\Candidate;
use Module\Ballot\Contract\PortableBallot;
use Module\Ballot\Contract\SettlementMode;
use Module\Ballot\Contract\TallyMode;

final class BallotsSectionTest extends SectionTestCase
{
    /** @var list<PortableBallot> */
    private array $restored = [];

    public function testABallotOfTheScopeTravelsWithTheVotesOfConsentingMembersOnly(): void
    {
        // Arrange
        $ballots = [$this->ballot(), $this->ballot(purpose: 'somebody.else'), $this->ballot(subject: new BallotSubject('event', 8))];
        $scope = new Scope(
            users: [3 => 'admin', 7 => 'user', 8 => 'user'],
            eventIds: [9],
            grants: [3 => DataCategory::cases(), 7 => [DataCategory::Interactions], 8 => [DataCategory::Attendance]],
            stewardEmail: 'steward@example.org',
        );

        // Act
        $rows = $this->section($ballots)->export($scope, $this->images());

        // Assert
        static::assertCount(1, $rows);
        static::assertSame('event.item.film', $rows[0]['purpose']);
        static::assertSame('event', $rows[0]['subject_type']);
        static::assertSame(9, $rows[0]['subject_ref']);
        static::assertSame('settled', $rows[0]['status']);
        static::assertSame('14', $rows[0]['winning_key']);
        static::assertSame('steward@example.org', $rows[0]['opened_by_email']);
        static::assertSame('user-3@example.org', $rows[0]['settled_by_email']);
        static::assertSame('2030-01-10T18:00:00+00:00', $rows[0]['deadline']);
        static::assertSame([['key' => '12', 'label' => 'Alien'], ['key' => '14', 'label' => 'Brazil']], $rows[0]['candidates']);
        static::assertSame([['email' => 'user-7@example.org', 'keys' => ['14']]], $rows[0]['votes']);
    }

    public function testABallotComesBackRekeyedWithItsOutcome(): void
    {
        // Arrange
        $scope = new Scope(
            users: [3 => 'admin', 7 => 'user'],
            eventIds: [9],
            grants: [3 => DataCategory::cases(), 7 => DataCategory::cases()],
            stewardEmail: 'user-3@example.org',
        );
        $rows = $this->section([$this->ballot()])->export($scope, $this->images());
        $context = $this->importContext();

        // Act
        $this->section()->import($rows, $context);

        // Assert
        static::assertCount(1, $this->restored);
        $ballot = $this->restored[0];
        static::assertEquals(new BallotSubject('event', 90), $ballot->subject);
        static::assertEquals([new Candidate('120', 'Alien'), new Candidate('140', 'Brazil')], $ballot->candidates);
        static::assertSame(BallotStatus::Settled, $ballot->status);
        static::assertSame('140', $ballot->winningKey);
        static::assertSame(30, $ballot->openedByUserId);
        static::assertSame(30, $ballot->settledByUserId);
        static::assertSame([70 => ['140']], $ballot->votes);
        static::assertSame(TallyMode::Single, $ballot->tallyMode);
        static::assertSame('2030-01-10 18:00', $ballot->deadline->format('Y-m-d H:i'));
        static::assertSame(1, $context->toSummary()->get('ballots', Outcome::Created));
    }

    public function testACandidateThatDoesNotArriveLeavesWithItsVotes(): void
    {
        // Arrange
        $row = $this->row(candidates: [['key' => '12', 'label' => 'Alien'], ['key' => '15', 'label' => 'Gone']], winningKey: null);
        $row['votes'] = [['email' => 'user-7@example.org', 'keys' => ['15']], ['email' => 'user-3@example.org', 'keys' => ['12', '15']]];
        $context = $this->importContext();

        // Act
        $this->section()->import([$row], $context);

        // Assert
        static::assertEquals([new Candidate('120', 'Alien')], $this->restored[0]->candidates);
        static::assertSame([30 => ['120']], $this->restored[0]->votes);
    }

    public function testALostWinnerOrSubjectDropsTheBallot(): void
    {
        // Arrange
        $context = $this->importContext();
        $rows = [
            $this->row(winningKey: '15', candidates: [['key' => '12', 'label' => 'Alien'], ['key' => '15', 'label' => 'Gone']]),
            $this->row(subjectRef: 8),
        ];

        // Act
        $this->section()->import($rows, $context);

        // Assert
        static::assertSame([], $this->restored);
        static::assertSame(2, $context->toSummary()->get('ballots', Outcome::Dropped));
    }

    public function testBallotsImportAfterThePluginSectionsSoAGlossaryCorrectionCandidateResolves(): void
    {
        // Act
        $order = $this->section()->getOrder();

        // Assert
        static::assertGreaterThan(100, $order);
    }

    public function testAPurposeNobodyClaimsIsSkipped(): void
    {
        // Arrange
        $context = $this->importContext();

        // Act
        $this->section()->import([$this->row(purpose: 'somebody.else')], $context);

        // Assert
        static::assertSame([], $this->restored);
        static::assertSame(1, $context->toSummary()->get('ballots', Outcome::Skipped));
    }

    /**
     * @param list<PortableBallot> $ballots
     */
    private function section(array $ballots = []): BallotsSection
    {
        $ballotInterface = $this->createStub(BallotInterface::class);
        $ballotInterface->method('exportAll')->willReturn($ballots);
        $ballotInterface
            ->method('restore')
            ->willReturnCallback(function (PortableBallot $ballot): int {
                $this->restored[] = $ballot;

                return count($this->restored);
            });

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findBy')->willReturnCallback(fn(array $criteria): array => array_map($this->user(...), $criteria['id']));

        return new BallotsSection($ballotInterface, $userRepository, [new ItemPurpose()]);
    }

    private function importContext(): ImportContext
    {
        $context = $this->context();
        $context->mapRef(Event::class, 9, $this->withId(new Event(), 90));
        $context->mapRef(User::class, 'user-3@example.org', $this->withId(new User(), 30));
        $context->mapRef(User::class, 'user-7@example.org', $this->withId(new User(), 70));
        $context->mapItems('film', [12 => 120, 14 => 140]);

        return $context;
    }

    private function ballot(string $purpose = 'event.item.film', BallotSubject $subject = new BallotSubject('event', 9)): PortableBallot
    {
        return new PortableBallot(
            purpose: $purpose,
            status: BallotStatus::Settled,
            tallyMode: TallyMode::Single,
            settlementMode: SettlementMode::Automatic,
            deadline: new DateTimeImmutable('2030-01-10 18:00:00+00:00'),
            openedByUserId: 5,
            createdAt: new DateTimeImmutable('2030-01-01 12:00:00+00:00'),
            candidates: [new Candidate('12', 'Alien'), new Candidate('14', 'Brazil')],
            votes: [7 => ['14'], 8 => ['12']],
            subject: $subject,
            winningKey: '14',
            settledByUserId: 3,
            settledAt: new DateTimeImmutable('2030-01-11 09:00:00+00:00'),
        );
    }

    /**
     * @param list<array{key: string, label: string}> $candidates
     * @return array<string, mixed>
     */
    private function row(
        string $purpose = 'event.item.film',
        int $subjectRef = 9,
        ?string $winningKey = '12',
        array $candidates = [['key' => '12', 'label' => 'Alien']],
    ): array {
        return [
            'purpose' => $purpose,
            'title' => null,
            'subject_type' => 'event',
            'subject_ref' => $subjectRef,
            'status' => $winningKey === null ? 'open' : 'settled',
            'tally_mode' => 'approval',
            'settlement_mode' => 'automatic',
            'deadline' => '2030-01-10T18:00:00+00:00',
            'opened_by_email' => 'user-3@example.org',
            'winning_key' => $winningKey,
            'tied_keys' => [],
            'settled_by_email' => null,
            'settled_at' => null,
            'created_at' => '2030-01-01T12:00:00+00:00',
            'candidates' => $candidates,
            'votes' => [],
        ];
    }

    private function user(int $id): User
    {
        $user = $this->withId(new User(), $id);
        $user->setEmail('user-' . $id . '@example.org');

        return $user;
    }
}
