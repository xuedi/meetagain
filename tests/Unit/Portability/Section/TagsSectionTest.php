<?php declare(strict_types=1);

namespace Tests\Unit\Portability\Section;

use App\Entity\ItemTag;
use App\Item\Tag\TypeRegistry;
use App\Portability\Outcome;
use App\Portability\Scope;
use App\Portability\Section\TagsSection;
use App\Repository\ItemTagRepository;

final class TagsSectionTest extends SectionTestCase
{
    public function testATagTreeSurvivesTheRoundTrip(): void
    {
        // Arrange
        $cuisine = $this->withId($this->tag('dish', ['en' => 'Cuisine', 'de' => 'Küche']), 3);
        $sicilian = $this->withId($this->tag('dish', ['en' => 'Sicilian'], $cuisine)->setPosition(4)->setManaged(true), 5);
        $exported = $this->section(found: [$cuisine, $sicilian])->export(new Scope(tagIds: ['dish' => [3, 5]]), $this->images());
        $context = $this->context();

        // Act
        $this->section()->import($exported, $context);

        // Assert
        static::assertSame([
            ['ref' => 3, 'item_type' => 'dish', 'parent_ref' => null, 'position' => 0, 'labels' => ['de' => 'Küche', 'en' => 'Cuisine'], 'managed' => false],
            ['ref' => 5, 'item_type' => 'dish', 'parent_ref' => 3, 'position' => 4, 'labels' => ['en' => 'Sicilian'], 'managed' => true],
        ], $exported);
        $parent = $context->resolveRef(ItemTag::class, 3);
        $child = $context->resolveRef(ItemTag::class, 5);
        static::assertSame([$parent, $child], $this->persisted);
        static::assertSame($parent, $child?->getParent());
        static::assertSame(['en' => 'Sicilian'], $child?->getLabels());
        static::assertTrue($child?->isManaged());
        static::assertSame(4, $child?->getPosition());
        static::assertSame(2, $context->toSummary()->get('tags', Outcome::Created));
    }

    public function testAChildIsImportedAfterItsParentWhateverTheRowOrder(): void
    {
        // Arrange
        $context = $this->context();
        $rows = [
            ['ref' => 5, 'item_type' => 'dish', 'parent_ref' => 3, 'labels' => ['en' => 'Sicilian']],
            ['ref' => 3, 'item_type' => 'dish', 'parent_ref' => null, 'labels' => ['en' => 'Cuisine']],
        ];

        // Act
        $this->section()->import($rows, $context);

        // Assert
        static::assertSame($context->resolveRef(ItemTag::class, 3), $context->resolveRef(ItemTag::class, 5)?->getParent());
        static::assertCount(2, $this->persisted);
    }

    public function testAnExistingTagWithTheSameParentAndALabelIsMatchedAndLeftAlone(): void
    {
        // Arrange
        $existing = $this->tag('dish', ['en' => 'Spicy']);
        $context = $this->context();

        // Act
        $this->section(existing: [$existing])->import([
            ['ref' => 8, 'item_type' => 'dish', 'parent_ref' => null, 'labels' => ['de' => 'Scharf', 'en' => ' Spicy ']],
        ], $context);

        // Assert
        static::assertSame($existing, $context->resolveRef(ItemTag::class, 8));
        static::assertSame(['en' => 'Spicy'], $existing->getLabels());
        static::assertSame([], $this->persisted);
        static::assertSame(1, $context->toSummary()->get('tags', Outcome::Matched));
    }

    public function testTheSameLabelUnderAnotherParentIsANewTag(): void
    {
        // Arrange
        $existingParent = $this->tag('dish', ['en' => 'Desserts']);
        $existing = $this->tag('dish', ['en' => 'Sweet'], $existingParent);
        $context = $this->context();

        // Act
        $this->section(existing: [$existingParent, $existing])->import([
            ['ref' => 9, 'item_type' => 'dish', 'parent_ref' => null, 'labels' => ['en' => 'Sweet']],
        ], $context);

        // Assert
        $imported = $this->onlyPersisted(ItemTag::class);
        static::assertNull($imported->getParent());
        static::assertSame($imported, $context->resolveRef(ItemTag::class, 9));
    }

    public function testRowsOfATypeThatIsNotTaggableHereAreSkipped(): void
    {
        // Arrange
        $context = $this->context();

        // Act
        $this->section(taggable: false)->import([
            ['ref' => 1, 'item_type' => 'karaoke', 'labels' => ['en' => 'Rock']],
            ['ref' => 2, 'item_type' => 'karaoke', 'labels' => ['en' => 'Pop']],
        ], $context);

        // Assert
        static::assertSame(['tags' => ['skipped' => 2]], $context->toSummary()->counts);
        static::assertSame([], $this->persisted);
    }

    public function testAParentOutsideTheExportedSetIsWrittenAsARoot(): void
    {
        // Arrange
        $parent = $this->withId($this->tag('dish', ['en' => 'Cuisine']), 3);
        $child = $this->withId($this->tag('dish', ['en' => 'Sicilian'], $parent), 5);

        // Act
        $exported = $this->section(found: [$child])->export(new Scope(tagIds: ['dish' => [5]]), $this->images());

        // Assert
        static::assertNull($exported[0]['parent_ref']);
    }

    /**
     * @param list<ItemTag> $found
     * @param list<ItemTag> $existing
     */
    private function section(array $found = [], array $existing = [], bool $taggable = true): TagsSection
    {
        $repository = $this->createStub(ItemTagRepository::class);
        $repository->method('findBy')->willReturn($found);
        $repository->method('findForType')->willReturn($existing);

        $typeRegistry = $this->createStub(TypeRegistry::class);
        $typeRegistry->method('has')->willReturn($taggable);

        return new TagsSection($this->entityManager(), $repository, $typeRegistry);
    }

    /**
     * @param array<string, string> $labels
     */
    private function tag(string $itemType, array $labels, ?ItemTag $parent = null): ItemTag
    {
        return new ItemTag()->setItemType($itemType)->setLabels($labels)->setParent($parent);
    }
}
