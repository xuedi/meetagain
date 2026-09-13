<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\ChangeProposal;
use App\Entity\Event;
use App\Entity\ItemTag;
use App\Entity\Location;
use App\Entity\User;
use App\Enum\ChangeProposalStatus;
use App\Enum\FieldResolution;
use App\Item\Tag\TypeRegistry;
use App\Portability\DataCategory;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\ChangeProposalsSection;
use App\Review\FieldChange;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;

final class ChangeProposalsSectionTest extends SectionTestCase
{
    public function testAnEventProposalSurvivesTheRoundTrip(): void
    {
        // Arrange
        $proposal = $this->proposal('event', 9, [new FieldChange('title', 'Go night', 'Go evening'), new FieldChange('teaser', 'Old', 'New', FieldResolution::Denied)]);
        $exported = $this->section([$proposal])->export($this->scope(eventIds: [9]), $this->images());

        $context = $this->context();
        $proposer = new User();
        $context->mapRef(Event::class, 9, $this->withId(new Event(), 90));
        $context->mapRef(User::class, 'member@example.org', $proposer);

        // Act
        $this->section()->import($exported, $context);

        // Assert
        $imported = $this->onlyPersisted(ChangeProposal::class);
        static::assertSame('event', $imported->getTargetType());
        static::assertSame(90, $imported->getTargetId());
        static::assertSame($proposer, $imported->getProposedBy());
        static::assertSame(ChangeProposalStatus::Pending, $imported->getStatus());
        static::assertSame('2030-01-06 10:00', $imported->getCreatedAt()->format('Y-m-d H:i'));
        static::assertEquals(
            [new FieldChange('title', 'Go night', 'Go evening'), new FieldChange('teaser', 'Old', 'New', FieldResolution::Denied)],
            $imported->getChanges(),
        );
        static::assertSame(1, $context->toSummary()->get('change_proposals', Outcome::Created));
    }

    public function testTheProposalRefTravelsAndMapsTheImportedProposal(): void
    {
        // Arrange
        $proposal = $this->withId($this->proposal('event', 9), 44);
        $exported = $this->section([$proposal])->export($this->scope(eventIds: [9]), $this->images());

        $context = $this->context();
        $context->mapRef(Event::class, 9, $this->withId(new Event(), 90));
        $context->mapRef(User::class, 'member@example.org', new User());

        // Act
        $this->section()->import($exported, $context);

        // Assert
        static::assertSame(44, $exported[0]['ref']);
        static::assertSame($this->onlyPersisted(ChangeProposal::class), $context->resolveRef(ChangeProposal::class, 44));
    }

    public function testAProposerWithoutTheInteractionsGrantIsLeftOut(): void
    {
        // Arrange
        $proposal = $this->proposal('event', 9);
        $scope = new Scope(users: [3 => 'user'], eventIds: [9], grants: [3 => [DataCategory::Attendance]]);

        // Act
        $exported = $this->section([$proposal])->export($scope, $this->images());

        // Assert
        static::assertSame([], $exported);
    }

    public function testAResolvedProposalStaysBehind(): void
    {
        // Arrange
        $proposal = $this->proposal('event', 9);
        $proposal->setStatus(ChangeProposalStatus::Approved);

        // Act
        $exported = $this->section([$proposal])->export($this->scope(eventIds: [9]), $this->images());

        // Assert
        static::assertSame([], $exported);
    }

    public function testAPluginProposalStaysBehind(): void
    {
        // Arrange
        $proposal = $this->proposal('glossary', 9);

        // Act
        $exported = $this->section([$proposal])->export($this->scope(eventIds: [9], tagIds: ['glossary' => [9]]), $this->images());

        // Assert
        static::assertSame([], $exported);
    }

    public function testALocationProposalFollowsTheVenueOfAnExportedEvent(): void
    {
        // Arrange
        $event = new Event();
        $event->setLocation($this->withId(new Location(), 4));
        $proposals = [$this->proposal('location', 4), $this->proposal('location', 5)];

        // Act
        $exported = $this->section($proposals, [$event])->export($this->scope(eventIds: [9]), $this->images());

        // Assert
        static::assertSame([4], array_column($exported, 'target_ref'));
    }

    public function testATagProposalTravelsWhenItsTagOrTheVocabularyIsInScope(): void
    {
        // Arrange
        $proposals = [$this->proposal('item_tag_dish', 7), $this->proposal('item_tag_dish', 8), $this->proposal('item_tag_dish', 0)];

        // Act
        $exported = $this->section($proposals)->export($this->scope(tagIds: ['dish' => [7]]), $this->images());

        // Assert
        static::assertSame([7, 0], array_column($exported, 'target_ref'));
    }

    public function testTheTagParentIsRekeyedThroughTheTagRefs(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(User::class, 'member@example.org', new User());
        $context->mapRef(ItemTag::class, 7, $this->withId(new ItemTag(), 70));
        $context->mapRef(ItemTag::class, 2, $this->withId(new ItemTag(), 20));
        $context->mapRef(ItemTag::class, 5, $this->withId(new ItemTag(), 50));
        $row = $this->tagRow(7, ['parent' => ['before' => '2', 'after' => '5', 'resolution' => null], 'label_en' => ['before' => 'Soup', 'after' => 'Soups', 'resolution' => null]]);

        // Act
        $this->section()->import([$row], $context);

        // Assert
        $imported = $this->onlyPersisted(ChangeProposal::class);
        static::assertSame(70, $imported->getTargetId());
        static::assertEquals([new FieldChange('parent', '20', '50'), new FieldChange('label_en', 'Soup', 'Soups')], $imported->getChanges());
    }

    public function testMovingATagToTheRootKeepsTheEmptyParent(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(User::class, 'member@example.org', new User());
        $context->mapRef(ItemTag::class, 7, $this->withId(new ItemTag(), 70));
        $context->mapRef(ItemTag::class, 2, $this->withId(new ItemTag(), 20));

        // Act
        $this->section()->import([$this->tagRow(7, ['parent' => ['before' => '2', 'after' => '']])], $context);

        // Assert
        static::assertEquals([new FieldChange('parent', '20', '')], $this->onlyPersisted(ChangeProposal::class)->getChanges());
    }

    public function testAParentTagMissingFromTheArchiveDropsTheProposal(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(User::class, 'member@example.org', new User());
        $context->mapRef(ItemTag::class, 7, $this->withId(new ItemTag(), 70));

        // Act
        $this->section()->import([$this->tagRow(7, ['parent' => ['before' => null, 'after' => '6']])], $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame(1, $context->toSummary()->get('change_proposals', Outcome::Dropped));
    }

    public function testTheVocabularyTargetStaysZero(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(User::class, 'member@example.org', new User());

        // Act
        $this->section()->import([$this->tagRow(0, ['child_en_1' => ['before' => null, 'after' => 'Desserts']])], $context);

        // Assert
        static::assertSame(0, $this->onlyPersisted(ChangeProposal::class)->getTargetId());
    }

    public function testATagTypeNotTaggableHereIsSkipped(): void
    {
        // Arrange
        $context = $this->context();

        // Act
        $this->section(taggable: false)->import([$this->tagRow(0, ['child_en_1' => ['after' => 'Desserts']])], $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame(1, $context->toSummary()->get('change_proposals', Outcome::Skipped));
    }

    public function testAnUnknownTargetTypeIsSkipped(): void
    {
        // Arrange
        $context = $this->context();

        // Act
        $this->section()->import([['target_type' => 'glossary', 'target_ref' => 3, 'email' => 'member@example.org', 'changes' => ['phrase' => ['after' => 'x']]]], $context);

        // Assert
        static::assertSame(1, $context->toSummary()->get('change_proposals', Outcome::Skipped));
    }

    public function testATargetOrProposerMissingFromTheArchiveIsDropped(): void
    {
        // Arrange
        $context = $this->context();
        $context->mapRef(Event::class, 9, $this->withId(new Event(), 90));
        $changes = ['title' => ['before' => 'a', 'after' => 'b']];
        $rows = [
            ['target_type' => 'event', 'target_ref' => 8, 'email' => 'member@example.org', 'changes' => $changes],
            ['target_type' => 'event', 'target_ref' => 9, 'email' => 'unknown@example.org', 'changes' => $changes],
        ];

        // Act
        $this->section()->import($rows, $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame(2, $context->toSummary()->get('change_proposals', Outcome::Dropped));
    }

    /**
     * @param list<ChangeProposal> $proposals
     * @param list<Event> $events
     */
    private function section(array $proposals = [], array $events = [], bool $taggable = true): ChangeProposalsSection
    {
        $proposalRepository = $this->createStub(EntityRepository::class);
        $proposalRepository->method('findBy')->willReturnCallback(static fn(array $criteria): array => array_values(array_filter(
            $proposals,
            static fn(ChangeProposal $proposal): bool => $proposal->getStatus() === $criteria['status'] && in_array($proposal->getTargetType(), $criteria['targetType'], true),
        )));
        $eventRepository = $this->createStub(EntityRepository::class);
        $eventRepository->method('findBy')->willReturn($events);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturnCallback(static fn(string $class): EntityRepository => $class === Event::class ? $eventRepository : $proposalRepository);
        $em->method('persist')->willReturnCallback(function (object $entity): void {
            $this->persisted[] = $entity;
        });

        $tagTypes = $this->createStub(TypeRegistry::class);
        $tagTypes->method('has')->willReturn($taggable);

        return new ChangeProposalsSection($em, $tagTypes);
    }

    /**
     * @param list<int> $eventIds
     * @param array<string, list<int>> $tagIds
     */
    private function scope(array $eventIds = [], array $tagIds = []): Scope
    {
        return new Scope(users: [3 => 'user'], eventIds: $eventIds, grants: [3 => [DataCategory::Interactions]], tagIds: $tagIds);
    }

    /**
     * @param list<FieldChange> $changes
     */
    private function proposal(string $targetType, int $targetId, array $changes = [new FieldChange('title', 'a', 'b')]): ChangeProposal
    {
        $proposer = $this->withId(new User(), 3);
        $proposer->setEmail('member@example.org');

        $proposal = new ChangeProposal();
        $proposal->setTargetType($targetType);
        $proposal->setTargetId($targetId);
        $proposal->setProposedBy($proposer);
        $proposal->setChanges($changes);
        $proposal->setCreatedAt(new DateTimeImmutable('2030-01-06 10:00'));

        return $proposal;
    }

    /**
     * @param array<string, array<string, string|null>> $changes
     * @return array<string, mixed>
     */
    private function tagRow(int $targetRef, array $changes): array
    {
        return ['target_type' => 'item_tag_dish', 'target_ref' => $targetRef, 'email' => 'member@example.org', 'changes' => $changes];
    }
}
