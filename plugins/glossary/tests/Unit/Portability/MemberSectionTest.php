<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Portability;

use App\Entity\ChangeProposal;
use App\Entity\ItemTag;
use App\Entity\User;
use App\Enum\ChangeProposalStatus;
use App\Enum\FieldResolution;
use App\Portability\DataCategory;
use App\Portability\ImportContext;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Repository\UserRepository;
use App\Review\FieldChange;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Plugin\Glossary\Entity\Glossary;
use Plugin\Glossary\Entity\TrainerCard;
use Plugin\Glossary\Entity\TrainerDay;
use Plugin\Glossary\Enum\CardState;
use Plugin\Glossary\Enum\Direction;
use Plugin\Glossary\Portability\MemberSection;
use Plugin\Glossary\Service\GlossaryService;
use Tests\Unit\Portability\Section\SectionTestCase;

final class MemberSectionTest extends SectionTestCase
{
    public function testCardsDaysAndProposalsSurviveTheRoundTrip(): void
    {
        // Arrange
        $member = $this->member(3, 'learner@example.org');
        $exported = $this->section(cards: [$this->card(3, 8)], days: [$this->day(3)], proposals: [$this->proposal($member, 8, [
            new FieldChange('tag', '3,5', '5'),
            new FieldChange('phrase', 'ni hao', 'nǐ hǎo', FieldResolution::Denied),
        ])], users: [$member])->export($this->scope(), $this->images());

        $context = $this->importContext();

        // Act
        $this->section()->import($exported, $context);

        // Assert
        $card = $this->onlyPersisted(TrainerCard::class);
        static::assertSame(30, $card->getUserId());
        static::assertSame(80, $card->getGlossary()->getId());
        static::assertSame(Direction::DefinitionToTerm, $card->getDirection());
        static::assertSame(CardState::Review, $card->getState());
        static::assertSame('2030-01-13 09:00', $card->getDueAt()?->format('Y-m-d H:i'));
        static::assertSame(21, $card->getIntervalDays());
        static::assertSame(2300, $card->getEasePermille());
        static::assertSame(4, $card->getRepetitions());
        static::assertSame(1, $card->getLapses());
        static::assertSame(3, $card->getTimesSeen());
        static::assertSame(2, $card->getTimesCorrect());
        static::assertTrue($card->isMarked());
        static::assertFalse($card->isSuspended());
        static::assertSame('2030-01-06 20:00', $card->getLastReviewedAt()?->format('Y-m-d H:i'));
        static::assertSame('2029-12-01 10:00', $card->getCreatedAt()->format('Y-m-d H:i'));

        $day = $this->onlyPersisted(TrainerDay::class);
        static::assertSame(30, $day->getUserId());
        static::assertSame('2030-01-06', $day->getDay()->format('Y-m-d'));
        static::assertSame([3, 2, 1], [$day->getReviewed(), $day->getCorrect(), $day->getNewStarted()]);

        $proposal = $this->onlyPersisted(ChangeProposal::class);
        static::assertSame('glossary', $proposal->getTargetType());
        static::assertSame(80, $proposal->getTargetId());
        static::assertSame(ChangeProposalStatus::Pending, $proposal->getStatus());
        static::assertEquals(
            [new FieldChange('tag', '30,50', '50'), new FieldChange('phrase', 'ni hao', 'nǐ hǎo', FieldResolution::Denied)],
            $proposal->getChanges(),
        );
        static::assertSame($proposal, $context->resolveRef(ChangeProposal::class, 44));

        $summary = $context->toSummary();
        static::assertSame(1, $summary->get(MemberSection::KIND_CARDS, Outcome::Created));
        static::assertSame(1, $summary->get(MemberSection::KIND_DAYS, Outcome::Created));
        static::assertSame(1, $summary->get(MemberSection::KIND_CHANGE_PROPOSALS, Outcome::Created));
    }

    public function testAMemberWhoGrantedNeitherCollectionsNorInteractionsIsLeftOut(): void
    {
        // Arrange
        $member = $this->member(3, 'learner@example.org');
        $section = $this->section(cards: [$this->card(3, 8)], days: [$this->day(3)], proposals: [$this->proposal($member, 8, [new FieldChange(
            'phrase',
            'a',
            'b',
        )])], users: [$member]);
        $scope = new Scope(users: [3 => 'user'], itemIds: ['glossary' => [8]], grants: [3 => [DataCategory::Attendance]]);

        // Act
        $exported = $section->export($scope, $this->images());

        // Assert
        static::assertSame([], $exported);
    }

    public function testCollectionsWithoutInteractionsKeepsTheCardsButNotTheProposals(): void
    {
        // Arrange
        $member = $this->member(3, 'learner@example.org');
        $section = $this->section(cards: [$this->card(3, 8)], days: [$this->day(3)], proposals: [$this->proposal($member, 8, [new FieldChange(
            'phrase',
            'a',
            'b',
        )])], users: [$member]);
        $scope = new Scope(users: [3 => 'user'], itemIds: ['glossary' => [8]], grants: [3 => [DataCategory::Collections]]);

        // Act
        $exported = $section->export($scope, $this->images());

        // Assert
        static::assertCount(1, $exported['cards']);
        static::assertCount(1, $exported['days']);
        static::assertSame([], $exported['change_proposals']);
    }

    public function testAProposalOnAnEntryOutsideTheScopeStaysBehind(): void
    {
        // Arrange
        $member = $this->member(3, 'learner@example.org');
        $section = $this->section(proposals: [$this->proposal($member, 99, [new FieldChange('phrase', 'a', 'b')])], users: [$member]);

        // Act
        $exported = $section->export($this->scope(), $this->images());

        // Assert
        static::assertSame([], $exported);
    }

    public function testAnUnresolvableTagDropsTheWholeProposal(): void
    {
        // Arrange
        $context = $this->importContext();
        $rows = [
            'change_proposals' => [[
                'ref' => 44,
                'target_ref' => 8,
                'email' => 'learner@example.org',
                'changes' => ['tag' => ['before' => '3', 'after' => '3,7', 'resolution' => null]],
            ]],
        ];

        // Act
        $this->section()->import($rows, $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame(1, $context->toSummary()->get(MemberSection::KIND_CHANGE_PROPOSALS, Outcome::Dropped));
    }

    public function testACardAndADayTheMemberAlreadyHasAreMatched(): void
    {
        // Arrange
        $context = $this->importContext();
        $rows = [
            'cards' => [['email' => 'learner@example.org', 'glossary_ref' => 8, 'direction' => 'definition_to_term']],
            'days' => [['email' => 'learner@example.org', 'day' => '2030-01-06T00:00:00+01:00']],
        ];
        $section = $this->section(existingCard: $this->card(30, 80), existingDay: $this->day(30));

        // Act
        $section->import($rows, $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame(1, $context->toSummary()->get(MemberSection::KIND_CARDS, Outcome::Matched));
        static::assertSame(1, $context->toSummary()->get(MemberSection::KIND_DAYS, Outcome::Matched));
    }

    public function testRowsWhoseMemberOrEntryDidNotArriveAreDropped(): void
    {
        // Arrange
        $context = $this->importContext();
        $rows = [
            'cards' => [['email' => 'stranger@example.org', 'glossary_ref' => 8, 'direction' => 'definition_to_term']],
            'days' => [['email' => 'stranger@example.org', 'day' => '2030-01-06T00:00:00+01:00']],
            'change_proposals' => [[
                'ref' => 44,
                'target_ref' => 99,
                'email' => 'learner@example.org',
                'changes' => ['phrase' => ['before' => 'a', 'after' => 'b']],
            ]],
        ];

        // Act
        $this->section()->import($rows, $context);

        // Assert
        $summary = $context->toSummary();
        static::assertSame(1, $summary->get(MemberSection::KIND_CARDS, Outcome::Dropped));
        static::assertSame(1, $summary->get(MemberSection::KIND_DAYS, Outcome::Dropped));
        static::assertSame(1, $summary->get(MemberSection::KIND_CHANGE_PROPOSALS, Outcome::Dropped));
    }

    public function testAScopeWithoutGlossaryEntriesExportsNothing(): void
    {
        // Act
        $exported = $this->section(cards: [$this->card(3, 8)])->export(new Scope(users: [3 => 'user'], grants: [3 => DataCategory::cases()]), $this->images());

        // Assert
        static::assertSame([], $exported);
    }

    /**
     * @param list<TrainerCard> $cards
     * @param list<TrainerDay> $days
     * @param list<ChangeProposal> $proposals
     * @param list<User> $users
     */
    private function section(
        array $cards = [],
        array $days = [],
        array $proposals = [],
        array $users = [],
        ?TrainerCard $existingCard = null,
        ?TrainerDay $existingDay = null,
    ): MemberSection {
        $cardRepository = $this->createStub(EntityRepository::class);
        $cardRepository->method('findBy')->willReturn($cards);
        $cardRepository->method('findOneBy')->willReturn($existingCard);

        $dayRepository = $this->createStub(EntityRepository::class);
        $dayRepository->method('findBy')->willReturn($days);
        $dayRepository->method('findOneBy')->willReturn($existingDay);

        $proposalRepository = $this->createStub(EntityRepository::class);
        $proposalRepository
            ->method('findBy')
            ->willReturnCallback(static fn(array $criteria): array => array_values(array_filter(
                $proposals,
                static fn(ChangeProposal $proposal): bool => (
                    $proposal->getStatus() === $criteria['status']
                    && $proposal->getTargetType() === $criteria['targetType']
                ),
            )));

        $repositories = [TrainerCard::class => $cardRepository, TrainerDay::class => $dayRepository, ChangeProposal::class => $proposalRepository];

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(static fn(string $class): EntityRepository => $repositories[$class]);
        $em->method('getReference')->willReturnCallback(fn(string $class, int $id): Glossary => $this->withId(new Glossary(), $id));
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('findBy')->willReturn($users);

        $glossaryService = $this->createStub(GlossaryService::class);
        $glossaryService->method('decodeTagIds')->willReturnCallback(static fn(?string $value): array => (
            $value === null || $value === '' ? [] : array_map(intval(...), explode(',', $value))
        ));
        $glossaryService
            ->method('encodeTagIds')
            ->willReturnCallback(static function (array $tagIds): ?string {
                sort($tagIds);

                return $tagIds === [] ? null : implode(',', $tagIds);
            });

        return new MemberSection($em, $userRepository, $glossaryService);
    }

    private function scope(): Scope
    {
        return new Scope(users: [3 => 'user'], itemIds: ['glossary' => [8]], grants: [3 => [DataCategory::Collections, DataCategory::Interactions]]);
    }

    private function importContext(): ImportContext
    {
        $context = $this->context();
        $context->mapRef(User::class, 'learner@example.org', $this->member(30, 'learner@example.org'));
        $context->mapItems('glossary', [8 => 80]);
        $context->mapRef(ItemTag::class, 3, $this->withId(new ItemTag(), 30));
        $context->mapRef(ItemTag::class, 5, $this->withId(new ItemTag(), 50));

        return $context;
    }

    private function member(int $id, string $email): User
    {
        $user = $this->withId(new User(), $id);
        $user->setEmail($email);

        return $user;
    }

    private function card(int $userId, int $entryId): TrainerCard
    {
        $card = new TrainerCard($userId, $this->withId(new Glossary(), $entryId), Direction::DefinitionToTerm, new DateTimeImmutable('2029-12-01 10:00'));
        $card->setState(CardState::Review);
        $card->setDueAt(new DateTimeImmutable('2030-01-13 09:00'));
        $card->setIntervalDays(21);
        $card->setEasePermille(2300);
        $card->setRepetitions(4);
        $card->setLapses(1);
        $card->setMarked(true);
        $card->recordAnswer(true, new DateTimeImmutable('2030-01-05 20:00'));
        $card->recordAnswer(false, new DateTimeImmutable('2030-01-06 19:00'));
        $card->recordAnswer(true, new DateTimeImmutable('2030-01-06 20:00'));

        return $card;
    }

    private function day(int $userId): TrainerDay
    {
        $day = new TrainerDay($userId, new DateTimeImmutable('2030-01-06'));
        $day->record(true, true);
        $day->record(true, false);
        $day->record(false, false);

        return $day;
    }

    /**
     * @param list<FieldChange> $changes
     */
    private function proposal(User $proposer, int $entryId, array $changes): ChangeProposal
    {
        $proposal = $this->withId(new ChangeProposal(), 44);
        $proposal->setTargetType('glossary');
        $proposal->setTargetId($entryId);
        $proposal->setProposedBy($proposer);
        $proposal->setChanges($changes);
        $proposal->setCreatedAt(new DateTimeImmutable('2030-01-04 10:00'));

        return $proposal;
    }
}
