<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Ballot\Purpose;

use App\Entity\ChangeProposal;
use App\Entity\Event;
use App\Entity\ItemTag;
use App\Entity\Location;
use App\Entity\User;
use App\Enum\ChangeProposalStatus;
use App\Portability\Ballot\Purpose\FieldPurpose;
use App\Portability\DataCategory;
use App\Portability\Scope;
use App\Review\FieldBallotService;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Module\Ballot\Contract\BallotSubject;
use Tests\Unit\Portability\Section\SectionTestCase;

final class FieldPurposeTest extends SectionTestCase
{
    private const string PURPOSE = FieldBallotService::PURPOSE;

    public function testABallotOverExportedProposalsOnAnExportedTargetIsInScope(): void
    {
        // Arrange
        $purpose = $this->purpose([$this->proposal(5)]);

        // Act
        $inScope = $purpose->inScope(self::PURPOSE, new BallotSubject('event', 9), ['0:title', '5:title'], $this->scope(eventIds: [9]));

        // Assert
        static::assertTrue($inScope);
    }

    public function testAProposalThatStaysBehindKeepsItsBallotBehind(): void
    {
        // Arrange
        $resolved = $this->proposal(5);
        $resolved->setStatus(ChangeProposalStatus::Approved);
        $withheld = $this->proposal(6, proposerId: 4);

        // Act
        $inScope = [
            $this->purpose([$resolved])->inScope(self::PURPOSE, new BallotSubject('event', 9), ['0:title', '5:title'], $this->scope(eventIds: [9])),
            $this->purpose([$withheld])->inScope(self::PURPOSE, new BallotSubject('event', 9), ['0:title', '6:title'], $this->scope(eventIds: [9])),
            $this->purpose([])->inScope(self::PURPOSE, new BallotSubject('event', 9), ['0:title', '7:title'], $this->scope(eventIds: [9])),
        ];

        // Assert
        static::assertSame([false, false, false], $inScope);
    }

    public function testTheTargetDecidesTheScope(): void
    {
        // Arrange
        $event = new Event();
        $event->setLocation($this->withId(new Location(), 4));
        $purpose = $this->purpose([], [$event]);
        $scope = $this->scope(eventIds: [9], tagIds: ['dish' => [7]], itemIds: ['glossary' => [3]]);

        // Act
        $inScope = [
            $purpose->inScope(self::PURPOSE, new BallotSubject('event', 8), ['0:title'], $scope),
            $purpose->inScope(self::PURPOSE, new BallotSubject('location', 4), ['0:name'], $scope),
            $purpose->inScope(self::PURPOSE, new BallotSubject('location', 5), ['0:name'], $scope),
            $purpose->inScope(self::PURPOSE, new BallotSubject('item_tag_dish', 0), ['0:child_en_1'], $scope),
            $purpose->inScope(self::PURPOSE, new BallotSubject('item_tag_dish', 8), ['0:parent'], $scope),
            $purpose->inScope(self::PURPOSE, new BallotSubject('glossary', 3), ['0:phrase'], $scope),
            $purpose->inScope(self::PURPOSE, null, ['0:title'], $scope),
        ];

        // Assert
        static::assertSame([false, true, false, true, false, true, false], $inScope);
    }

    public function testEveryKindOfTargetIsRekeyed(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(Event::class, 9, $this->withId(new Event(), 90));
        $context->mapRef(Location::class, 4, $this->withId(new Location(), 40));
        $context->mapRef(ItemTag::class, 7, $this->withId(new ItemTag(), 70));
        $context->mapItems('glossary', [3 => 30]);
        $purpose = $this->purpose();

        // Act
        $subjects = [
            $purpose->importSubject(new BallotSubject('event', 9), $context),
            $purpose->importSubject(new BallotSubject('location', 4), $context),
            $purpose->importSubject(new BallotSubject('item_tag_dish', 7), $context),
            $purpose->importSubject(new BallotSubject('item_tag_dish', 0), $context),
            $purpose->importSubject(new BallotSubject('glossary', 3), $context),
            $purpose->importSubject(new BallotSubject('event', 8), $context),
        ];

        // Assert
        static::assertEquals([
            new BallotSubject('event', 90),
            new BallotSubject('location', 40),
            new BallotSubject('item_tag_dish', 70),
            new BallotSubject('item_tag_dish', 0),
            new BallotSubject('glossary', 30),
            null,
        ], $subjects);
    }

    public function testProposalKeysAreRekeyedAndLeavingTheValueAloneStays(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(ChangeProposal::class, 5, $this->withId(new ChangeProposal(), 50));
        $purpose = $this->purpose();

        // Act
        $keys = [
            $purpose->importKey(self::PURPOSE, '0:title', $context),
            $purpose->importKey(self::PURPOSE, '5:title', $context),
            $purpose->importKey(self::PURPOSE, '6:title', $context),
            $purpose->importKey(self::PURPOSE, 'title', $context),
        ];

        // Assert
        static::assertSame(['0:title', '50:title', null, null], $keys);
    }

    /**
     * @param list<ChangeProposal> $proposals
     * @param list<Event> $events
     */
    private function purpose(array $proposals = [], array $events = []): FieldPurpose
    {
        $proposalRepository = $this->createStub(EntityRepository::class);
        $proposalRepository->method('findBy')->willReturnCallback(static fn(array $criteria): array => array_values(array_filter(
            $proposals,
            static fn(ChangeProposal $proposal): bool => in_array($proposal->getId(), $criteria['id'], true),
        )));
        $eventRepository = $this->createStub(EntityRepository::class);
        $eventRepository->method('findBy')->willReturn($events);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(static fn(string $class): EntityRepository => $class === Event::class ? $eventRepository : $proposalRepository);

        return new FieldPurpose($em);
    }

    /**
     * @param list<int> $eventIds
     * @param array<string, list<int>> $tagIds
     * @param array<string, list<int>> $itemIds
     */
    private function scope(array $eventIds = [], array $tagIds = [], array $itemIds = []): Scope
    {
        return new Scope(
            users: [3 => 'user', 4 => 'user'],
            eventIds: $eventIds,
            itemIds: $itemIds,
            grants: [3 => [DataCategory::Interactions], 4 => [DataCategory::Attendance]],
            tagIds: $tagIds,
        );
    }

    private function proposal(int $id, int $proposerId = 3): ChangeProposal
    {
        $proposal = $this->withId(new ChangeProposal(), $id);
        $proposal->setProposedBy($this->withId(new User(), $proposerId));

        return $proposal;
    }
}
