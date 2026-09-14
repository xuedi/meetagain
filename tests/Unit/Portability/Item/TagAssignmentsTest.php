<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Item;

use App\Entity\ItemTag;
use App\Entity\ItemTagAssignment;
use App\Portability\ImportContext;
use App\Portability\Item\TagAssignments;
use App\Portability\Outcome;
use App\Repository\ItemTagAssignmentRepository;
use Tests\Unit\Portability\Section\SectionTestCase;

final class TagAssignmentsTest extends SectionTestCase
{
    public function testExportKeepsOnlyTheTagsTheScopeAllowsKeyedBySourceItemId(): void
    {
        // Arrange
        $repository = $this->createStub(ItemTagAssignmentRepository::class);
        $repository->method('tagIdsForItems')->willReturn([14 => [9], 12 => [5, 9, 2]]);

        // Act
        $block = $this->assignments($repository)->export('dish', [12, 14], [2, 5]);

        // Assert
        static::assertSame(['tags' => [12 => [2, 5]]], $block);
    }

    public function testArchivedTagIdsAreRekeyedToTheImportedTags(): void
    {
        // Arrange
        $context = $this->contextWithTags();

        // Act
        $this->assignments()->import('dish', ['tags' => [12 => [2, 5]]], [12 => 91], $context);

        // Assert
        $written = array_map(
            static fn(ItemTagAssignment $assignment): array => [$assignment->getItemType(), $assignment->getItemId(), $assignment->getTag()?->getId()],
            $this->persisted,
        );
        static::assertSame([['dish', 91, 40], ['dish', 91, 41]], $written);
        static::assertSame(2, $context->toSummary()->get(TagAssignments::KIND, Outcome::Created));
    }

    public function testAnArchivedTagIdWithoutAnImportedTagIsDropped(): void
    {
        // Arrange
        $context = $this->contextWithTags();

        // Act
        $this->assignments()->import('dish', ['tags' => [12 => [2, 98]]], [12 => 91], $context);

        // Assert
        static::assertCount(1, $this->persisted);
        static::assertSame(1, $context->toSummary()->get(TagAssignments::KIND, Outcome::Dropped));
    }

    public function testATagOfAnotherItemTypeIsDropped(): void
    {
        // Arrange
        $context = $this->contextWithTags();

        // Act
        $this->assignments()->import('book', ['tags' => [12 => [2]]], [12 => 91], $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame(1, $context->toSummary()->get(TagAssignments::KIND, Outcome::Dropped));
    }

    public function testEveryAssignmentOfARefMissingFromTheMapIsDropped(): void
    {
        // Arrange
        $context = $this->contextWithTags();

        // Act
        $this->assignments()->import('dish', ['tags' => [77 => [2, 5]]], [12 => 91], $context);

        // Assert
        static::assertSame([], $this->persisted);
        static::assertSame(2, $context->toSummary()->get(TagAssignments::KIND, Outcome::Dropped));
    }

    public function testATagTheItemAlreadyCarriesIsKeptAndNotWrittenTwice(): void
    {
        // Arrange
        $repository = $this->createStub(ItemTagAssignmentRepository::class);
        $repository->method('tagIdsFor')->willReturn([40]);
        $context = $this->contextWithTags();

        // Act
        $this->assignments($repository)->import('dish', ['tags' => [12 => [2, 5, 5]]], [12 => 91], $context);

        // Assert
        static::assertSame(41, $this->onlyPersisted(ItemTagAssignment::class)->getTag()?->getId());
        static::assertSame(2, $context->toSummary()->get(TagAssignments::KIND, Outcome::Matched));
    }

    private function assignments(?ItemTagAssignmentRepository $repository = null): TagAssignments
    {
        return new TagAssignments($repository ?? $this->createStub(ItemTagAssignmentRepository::class), $this->entityManager());
    }

    private function contextWithTags(): ImportContext
    {
        $context = $this->context();
        $context->mapRef(ItemTag::class, 2, $this->withId(new ItemTag()->setItemType('dish'), 40));
        $context->mapRef(ItemTag::class, 5, $this->withId(new ItemTag()->setItemType('dish'), 41));

        return $context;
    }
}
