<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\Event;
use App\Entity\ItemTag;
use App\Entity\Location;
use App\Entity\User;
use App\Item\Tag\TypeRegistry;
use App\Portability\DataCategory;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\ChangeProposalsSection;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Module\Proposal\Contract\Change\ChangeInterface;
use Module\Proposal\Contract\Change\FieldChange;
use Module\Proposal\Contract\Change\FieldResolution;
use Module\Proposal\Contract\Change\PortableChange;
use Module\Proposal\Contract\Change\View;
use Module\Proposal\Contract\Status;

final class ChangeProposalsSectionTest extends SectionTestCase
{
    private const int RESTORED_ID = 440;

    /** @var list<PortableChange> */
    private array $restored = [];

    public function testAnEventProposalSurvivesTheRoundTrip(): void
    {
        // Arrange
        $proposal = $this->proposal('event', 9, [
            new FieldChange('title', 'Go night', 'Go evening'),
            new FieldChange('teaser', 'Old', 'New', FieldResolution::Denied),
        ]);
        $exported = $this->section([$proposal])->export($this->scope(eventIds: [9]), $this->images());

        $context = $this->context();
        $context->mapRef(Event::class, 9, $this->withId(new Event(), 90));
        $context->mapRef(User::class, 'member@example.org', $this->withId(new User(), 30));

        // Act
        $this->section()->import($exported, $context);

        // Assert
        $imported = $this->onlyRestored();
        static::assertSame('event', $imported->targetType);
        static::assertSame(90, $imported->targetId);
        static::assertSame(30, $imported->proposerId);
        static::assertSame('2030-01-06 10:00', $imported->createdAt->format('Y-m-d H:i'));
        static::assertEquals(
            [new FieldChange('title', 'Go night', 'Go evening'), new FieldChange('teaser', 'Old', 'New', FieldResolution::Denied)],
            $imported->changes,
        );
        static::assertSame(1, $context->toSummary()->get('change_proposals', Outcome::Created));
    }

    public function testTheProposalRefTravelsAndMapsTheImportedProposal(): void
    {
        // Arrange
        $proposal = $this->proposal('event', 9, id: 44);
        $exported = $this->section([$proposal])->export($this->scope(eventIds: [9]), $this->images());

        $context = $this->context();
        $context->mapRef(Event::class, 9, $this->withId(new Event(), 90));
        $context->mapRef(User::class, 'member@example.org', $this->withId(new User(), 30));

        // Act
        $this->section()->import($exported, $context);

        // Assert
        static::assertSame(44, $exported[0]['ref']);
        static::assertSame(self::RESTORED_ID, $context->resolveId(ChangeProposalsSection::REFS, 44));
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
        $proposal = $this->proposal('event', 9, status: Status::Approved);

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
        $row = $this->tagRow(7, [
            'parent' => ['before' => '2', 'after' => '5', 'resolution' => null],
            'label_en' => ['before' => 'Soup', 'after' => 'Soups', 'resolution' => null],
        ]);

        // Act
        $this->section()->import([$row], $context);

        // Assert
        $imported = $this->onlyRestored();
        static::assertSame(70, $imported->targetId);
        static::assertEquals([new FieldChange('parent', '20', '50'), new FieldChange('label_en', 'Soup', 'Soups')], $imported->changes);
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
        static::assertEquals([new FieldChange('parent', '20', '')], $this->onlyRestored()->changes);
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
        static::assertSame([], $this->restored);
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
        static::assertSame(0, $this->onlyRestored()->targetId);
    }

    public function testATagTypeNotTaggableHereIsSkipped(): void
    {
        // Arrange
        $context = $this->context();

        // Act
        $this->section(taggable: false)->import([$this->tagRow(0, ['child_en_1' => ['after' => 'Desserts']])], $context);

        // Assert
        static::assertSame([], $this->restored);
        static::assertSame(1, $context->toSummary()->get('change_proposals', Outcome::Skipped));
    }

    public function testAnUnknownTargetTypeIsSkipped(): void
    {
        // Arrange
        $context = $this->context();

        // Act
        $this->section()->import([[
            'target_type' => 'glossary',
            'target_ref' => 3,
            'email' => 'member@example.org',
            'changes' => ['phrase' => ['after' => 'x']],
        ]], $context);

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
        static::assertSame([], $this->restored);
        static::assertSame(2, $context->toSummary()->get('change_proposals', Outcome::Dropped));
    }

    /**
     * @param list<View> $proposals
     * @param list<Event> $events
     */
    private function section(array $proposals = [], array $events = [], bool $taggable = true): ChangeProposalsSection
    {
        $eventRepository = $this->createStub(EntityRepository::class);
        $eventRepository->method('findBy')->willReturn($events);

        $proposer = $this->withId(new User(), 3);
        $proposer->setEmail('member@example.org');

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($eventRepository);
        $em->method('find')->willReturnCallback(static fn(string $class, mixed $id): ?User => $id === 3 ? $proposer : null);

        $tagTypes = $this->createStub(TypeRegistry::class);
        $tagTypes->method('has')->willReturn($taggable);

        $changes = $this->createStub(ChangeInterface::class);
        $changes
            ->method('exportPending')
            ->willReturnCallback(static fn(array $targetTypes): array => array_values(array_filter(
                $proposals,
                static fn(View $proposal): bool => $proposal->status === Status::Pending && in_array($proposal->targetType, $targetTypes, true),
            )));
        $changes
            ->method('restore')
            ->willReturnCallback(function (PortableChange $change): int {
                $this->restored[] = $change;

                return self::RESTORED_ID;
            });

        return new ChangeProposalsSection($em, $tagTypes, $changes);
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
    private function proposal(
        string $targetType,
        int $targetId,
        array $changes = [new FieldChange('title', 'a', 'b')],
        int $id = 1,
        Status $status = Status::Pending,
    ): View {
        return new View($id, $targetType, $targetId, 3, 'member', $status, new DateTimeImmutable('2030-01-06 10:00'), $changes, []);
    }

    private function onlyRestored(): PortableChange
    {
        static::assertCount(1, $this->restored);

        return $this->restored[0];
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
