<?php declare(strict_types=1);

namespace Plugin\Boardgames\Tests\Unit\Portability;

use App\Entity\Event;
use App\Entity\User;
use App\Portability\DataCategory;
use App\Portability\Outcome;
use App\Portability\Scope;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Plugin\Boardgames\Entity\BringRequest;
use Plugin\Boardgames\Entity\Game;
use Plugin\Boardgames\Entity\GameOwnership;
use Plugin\Boardgames\Entity\GamePledge;
use Plugin\Boardgames\Enum\CopyCondition;
use Plugin\Boardgames\Enum\PledgeStatus;
use Plugin\Boardgames\Enum\RequestStatus;
use Plugin\Boardgames\Portability\ShelfSection;
use Tests\Unit\Portability\Section\SectionTestCase;

final class ShelfSectionTest extends SectionTestCase
{
    public function testShelvesPledgesAndRequestsSurviveTheRoundTrip(): void
    {
        // Arrange
        $game = $this->withId(new Game(), 4);
        $event = $this->withId(new Event(), 9);
        $ada = $this->user(1, 'ada@example.org');
        $bo = $this->user(2, 'bo@example.org');
        $exported = $this->section([
            GameOwnership::class => [$this->ownership($ada, $game)],
            GamePledge::class => [new GamePledge()->setEvent($event)->setGame($game)->setUser($ada)->setStatus(PledgeStatus::Withdrawn)->setCreatedAt(new DateTimeImmutable('2026-01-06 10:00'))],
            BringRequest::class => [$this->request($event, $game, $bo, $ada)],
        ])->export($this->scope([1, 2]), $this->images());

        $importedGame = $this->withId(new Game(), 44);
        $importedEvent = new Event();
        $importedAda = $this->user(11, 'ada@example.org');
        $importedBo = $this->user(12, 'bo@example.org');
        $context = $this->context();
        $context->mapItems('boardgame', [4 => 44]);
        $context->mapRef(Event::class, 9, $importedEvent);
        $context->mapRef(User::class, 'ada@example.org', $importedAda);
        $context->mapRef(User::class, 'bo@example.org', $importedBo);

        // Act
        $this->section(game: $importedGame)->import($exported, $context);

        // Assert
        $ownership = $this->onlyPersisted(GameOwnership::class);
        static::assertSame($importedAda, $ownership->getUser());
        static::assertSame($importedGame, $ownership->getGame());
        static::assertSame('de', $ownership->getCopyLanguage());
        static::assertSame(CopyCondition::Good, $ownership->getCopyCondition());
        static::assertSame('Sleeved', $ownership->getNotes());
        static::assertTrue($ownership->isCanTeach());
        static::assertFalse($ownership->isWillingToBring());
        static::assertFalse($ownership->isPublic());
        static::assertSame('2019-05-01', $ownership->getAcquiredAt()?->format('Y-m-d'));

        $pledge = $this->onlyPersisted(GamePledge::class);
        static::assertSame($importedEvent, $pledge->getEvent());
        static::assertSame(PledgeStatus::Withdrawn, $pledge->getStatus());
        static::assertSame('2026-01-06 10:00', $pledge->getCreatedAt()?->format('Y-m-d H:i'));

        $request = $this->onlyPersisted(BringRequest::class);
        static::assertSame($importedBo, $request->getRequestedBy());
        static::assertSame($importedAda, $request->getOwnerUser());
        static::assertSame(RequestStatus::Accepted, $request->getStatus());
        static::assertSame('Could you bring it?', $request->getMessage());
        static::assertSame('2026-01-07 12:00', $request->getRespondedAt()?->format('Y-m-d H:i'));
    }

    public function testRowsOfAMemberWithoutTheCollectionsGrantStayBehind(): void
    {
        // Arrange
        $game = $this->withId(new Game(), 4);
        $event = $this->withId(new Event(), 9);
        $ada = $this->user(1, 'ada@example.org');
        $bo = $this->user(2, 'bo@example.org');
        $section = $this->section([
            GameOwnership::class => [$this->ownership($ada, $game), $this->ownership($bo, $game)],
            GamePledge::class => [new GamePledge()->setEvent($event)->setGame($game)->setUser($bo)->setCreatedAt(new DateTimeImmutable())],
            BringRequest::class => [$this->request($event, $game, $ada, $bo)],
        ]);

        // Act
        $exported = $section->export($this->scope([1]), $this->images());

        // Assert
        static::assertSame(['ada@example.org'], array_column($exported['ownerships'], 'email'));
        static::assertSame([], $exported['pledges']);
        static::assertSame([], $exported['requests']);
    }

    public function testNothingIsExportedWithoutGamesInTheScope(): void
    {
        // Arrange
        $section = $this->section([GameOwnership::class => [$this->ownership($this->user(1, 'ada@example.org'), $this->withId(new Game(), 4))]]);

        // Act
        $exported = $section->export(new Scope(users: [1 => 'user'], grants: [1 => [DataCategory::Collections]]), $this->images());

        // Assert
        static::assertSame([], $exported);
    }

    public function testAnOwnershipTheMemberAlreadyHasIsMatched(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapItems('boardgame', [4 => 44]);
        $context->mapRef(User::class, 'ada@example.org', $this->user(11, 'ada@example.org'));

        // Act
        $this->section(game: $this->withId(new Game(), 44), existingOwnership: new GameOwnership())
            ->import(['ownerships' => [['game_ref' => 4, 'email' => 'ada@example.org']]], $context);

        // Assert
        static::assertSame(1, $context->toSummary()->get(ShelfSection::KIND_OWNERSHIPS, Outcome::Matched));
        static::assertSame([], $this->persisted);
    }

    public function testRowsOfAGameTypeThisInstanceDidNotImportAreSkipped(): void
    {
        // Arrange
        $context = $this->context();
        $rows = [
            'ownerships' => [['game_ref' => 4, 'email' => 'ada@example.org']],
            'pledges' => [['event_ref' => 9, 'game_ref' => 4, 'email' => 'ada@example.org'], ['event_ref' => 9, 'game_ref' => 5, 'email' => 'ada@example.org']],
            'requests' => [],
        ];

        // Act
        $this->section()->import($rows, $context);

        // Assert
        $summary = $context->toSummary();
        static::assertSame(1, $summary->get(ShelfSection::KIND_OWNERSHIPS, Outcome::Skipped));
        static::assertSame(2, $summary->get(ShelfSection::KIND_PLEDGES, Outcome::Skipped));
        static::assertSame([], $this->persisted);
    }

    public function testRowsWhoseGameEventOrMemberDidNotArriveAreDropped(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapItems('boardgame', [4 => 44]);
        $context->mapRef(User::class, 'ada@example.org', $this->user(11, 'ada@example.org'));
        $rows = [
            'ownerships' => [['game_ref' => 5, 'email' => 'ada@example.org']],
            'pledges' => [['event_ref' => 9, 'game_ref' => 4, 'email' => 'ada@example.org']],
            'requests' => [['event_ref' => 9, 'game_ref' => 4, 'requested_by_email' => 'ada@example.org', 'owner_email' => 'gone@example.org']],
        ];

        // Act
        $this->section(game: $this->withId(new Game(), 44))->import($rows, $context);

        // Assert
        $summary = $context->toSummary();
        static::assertSame(1, $summary->get(ShelfSection::KIND_OWNERSHIPS, Outcome::Dropped));
        static::assertSame(1, $summary->get(ShelfSection::KIND_PLEDGES, Outcome::Dropped));
        static::assertSame(1, $summary->get(ShelfSection::KIND_REQUESTS, Outcome::Dropped));
        static::assertSame([], $this->persisted);
    }

    /**
     * @param array<class-string, list<object>> $rowsByClass
     */
    private function section(array $rowsByClass = [], ?Game $game = null, ?GameOwnership $existingOwnership = null): ShelfSection
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(function (string $class) use ($rowsByClass, $existingOwnership): EntityRepository {
            $repository = $this->createStub(EntityRepository::class);
            $repository->method('findBy')->willReturn($rowsByClass[$class] ?? []);
            $repository->method('findOneBy')->willReturn($class === GameOwnership::class ? $existingOwnership : null);

            return $repository;
        });
        $em->method('find')->willReturn($game);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        return new ShelfSection($em);
    }

    /**
     * @param list<int> $grantedUserIds
     */
    private function scope(array $grantedUserIds): Scope
    {
        return new Scope(
            users: [1 => 'user', 2 => 'user'],
            eventIds: [9],
            itemIds: ['boardgame' => [4]],
            grants: array_fill_keys($grantedUserIds, [DataCategory::Collections]),
        );
    }

    private function ownership(User $user, Game $game): GameOwnership
    {
        return new GameOwnership()
            ->setUser($user)
            ->setGame($game)
            ->setCopyLanguage('de')
            ->setCopyCondition(CopyCondition::Good)
            ->setNotes('Sleeved')
            ->setCanTeach(true)
            ->setWillingToBring(false)
            ->setPublic(false)
            ->setAcquiredAt(new DateTimeImmutable('2019-05-01'))
            ->setCreatedAt(new DateTimeImmutable('2026-01-05 10:00'));
    }

    private function request(Event $event, Game $game, User $requester, User $owner): BringRequest
    {
        return new BringRequest()
            ->setEvent($event)
            ->setGame($game)
            ->setRequestedBy($requester)
            ->setOwnerUser($owner)
            ->setStatus(RequestStatus::Accepted)
            ->setMessage('Could you bring it?')
            ->setCreatedAt(new DateTimeImmutable('2026-01-06 11:00'))
            ->setRespondedAt(new DateTimeImmutable('2026-01-07 12:00'));
    }

    private function user(int $id, string $email): User
    {
        $user = $this->withId(new User(), $id);
        $user->setEmail($email);

        return $user;
    }
}
