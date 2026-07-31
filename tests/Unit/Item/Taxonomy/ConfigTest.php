<?php declare(strict_types=1);

namespace Tests\Unit\Item\Taxonomy;

use App\Item\Taxonomy\Axis;
use App\Item\Taxonomy\CategoryDefinition;
use App\Item\Taxonomy\Config;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ConfigTest extends TestCase
{
    public function testNeutralDefaultIsDisabledAndEmpty(): void
    {
        // Arrange + Act
        $config = new Config();

        // Assert
        static::assertFalse($config->isCategoriesEnabled());
        static::assertFalse($config->isTagsEnabled());
        static::assertSame([], $config->categoryDefinitions());
        static::assertSame([], $config->tagDefinitions());
    }

    public function testNormalizeAssignsIdsDropsBlankRowsAcrossBothLists(): void
    {
        // Arrange
        $config = (new Config())
            ->setCategories([
                ['id' => 5, 'labels' => ['en' => 'Existing']],
                ['id' => '', 'labels' => ['en' => 'Fresh']],
                ['id' => '', 'labels' => ['en' => '   ', 'de' => '']],
            ])
            ->setTags([
                ['id' => '', 'labels' => ['en' => 'Spicy']],
            ]);

        // Act
        $config->normalize();

        // Assert
        $categories = $config->categoryDefinitions();
        static::assertCount(2, $categories);
        static::assertSame(5, $categories[0]->id);
        static::assertSame(6, $categories[1]->id);
        static::assertSame('Fresh', $categories[1]->labels['en']);

        $tags = $config->tagDefinitions();
        static::assertCount(1, $tags);
        static::assertSame(0, $tags[0]->id);
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        // Arrange
        $config = (new Config())
            ->setCategoriesEnabled(true)
            ->setTagsEnabled(true)
            ->setCategories([['id' => 0, 'labels' => ['en' => 'Greeting', 'de' => 'Gruss']]])
            ->setTags([['id' => 0, 'labels' => ['en' => 'Formal']]]);

        // Act
        $restored = Config::fromArray($config->toArray());

        // Assert
        static::assertTrue($restored->isCategoriesEnabled());
        static::assertTrue($restored->isTagsEnabled());
        static::assertSame('Greeting', $restored->categoryLabel(0, 'en', 'en'));
        static::assertSame('Gruss', $restored->categoryLabel(0, 'de', 'en'));
        static::assertSame('Formal', $restored->tagLabel(0, 'en', 'en'));
    }

    public function testCategoryOptionsAreLabelToId(): void
    {
        // Arrange
        $config = (new Config())->setCategoriesEnabled(true)->setCategories([
            ['id' => 3, 'labels' => ['en' => 'Slang']],
            ['id' => 7, 'labels' => ['en' => 'Idioms']],
        ]);

        // Act
        $options = $config->categoryOptions('en', 'en');

        // Assert
        static::assertSame(['Slang' => 3, 'Idioms' => 7], $options);
    }

    public function testHasCategoryAndHasTag(): void
    {
        // Arrange
        $config = (new Config())
            ->setCategories([['id' => 2, 'labels' => ['en' => 'A']]])
            ->setTags([['id' => 4, 'labels' => ['en' => 'B']]]);

        // Act + Assert
        static::assertTrue($config->hasCategory(2));
        static::assertFalse($config->hasCategory(99));
        static::assertTrue($config->hasTag(4));
        static::assertFalse($config->hasTag(99));
    }

    public function testNormalizeAllocatesGroupIdsIndependentlyOfDefinitionIds(): void
    {
        // Arrange
        $config = (new Config())
            ->setCategoryGroups([['id' => '', 'labels' => ['en' => 'Meat']]])
            ->setCategories([['id' => 8, 'labels' => ['en' => 'Chicken']]]);

        // Act
        $config->normalize();

        // Assert
        $groups = $config->categoryGroupDefinitions();
        static::assertCount(1, $groups);
        static::assertSame(0, $groups[0]->id);
        static::assertSame(8, $config->categoryDefinitions()[0]->id);
    }

    public function testNormalizeResolvesAClientTokenIntoTheAllocatedGroupId(): void
    {
        // Arrange
        $config = (new Config())
            ->setCategoryGroups([
                ['id' => 4, 'labels' => ['en' => 'Meat']],
                ['id' => 'n1', 'labels' => ['en' => 'Fish']],
            ])
            ->setCategories([
                ['id' => '', 'labels' => ['en' => 'Salmon'], 'group' => 'n1'],
                ['id' => '', 'labels' => ['en' => 'Beef'], 'group' => 4],
            ]);

        // Act
        $config->normalize();

        // Assert
        static::assertSame([4, 5], array_column($config->getCategoryGroups(), 'id'));
        static::assertSame(5, $config->categoryDefinitions()[0]->group);
        static::assertSame(4, $config->categoryDefinitions()[1]->group);
    }

    public function testNormalizeDropsAReferenceToANonexistentGroup(): void
    {
        // Arrange
        $config = (new Config())->setCategories([['id' => 1, 'labels' => ['en' => 'Chicken'], 'group' => 9]]);

        // Act
        $config->normalize();

        // Assert
        static::assertNull($config->categoryDefinitions()[0]->group);
    }

    public function testClearingAGroupLabelDeletesItAndDemotesItsMembers(): void
    {
        // Arrange
        $config = (new Config())
            ->setCategoryGroups([['id' => 0, 'labels' => ['en' => '  ']]])
            ->setCategories([['id' => 1, 'labels' => ['en' => 'Chicken'], 'group' => 0]]);

        // Act
        $config->normalize();

        // Assert
        static::assertSame([], $config->categoryGroupDefinitions());
        static::assertNull($config->categoryDefinitions()[0]->group);
    }

    public function testTagsCarryNoGroupOfTheirOwn(): void
    {
        // Arrange
        $config = (new Config())
            ->setCategoryGroups([['id' => '', 'labels' => ['en' => 'Meat']]])
            ->setTags([['id' => '', 'labels' => ['en' => 'Spicy'], 'group' => 0]]);

        // Act
        $config->normalize();

        // Assert
        static::assertSame(0, $config->categoryGroupDefinitions()[0]->id);
        static::assertArrayNotHasKey('group', $config->toArray()['tags'][0]);
        static::assertArrayNotHasKey('tagGroups', $config->toArray());
    }

    public function testToArrayFromArrayRoundTripsGroups(): void
    {
        // Arrange
        $config = (new Config())
            ->setCategoryGroups([['id' => 3, 'labels' => ['en' => 'Meat', 'de' => 'Fleisch']]])
            ->setCategories([
                ['id' => 0, 'labels' => ['en' => 'Chicken'], 'group' => 3],
                ['id' => 1, 'labels' => ['en' => 'Soup']],
            ]);

        // Act
        $raw = $config->toArray();
        $restored = Config::fromArray($raw);

        // Assert
        static::assertSame([['id' => 3, 'labels' => ['en' => 'Meat', 'de' => 'Fleisch']]], $raw['categoryGroups']);
        static::assertArrayNotHasKey('group', $raw['categories'][1]);
        static::assertSame('Meat', $restored->categoryGroupDefinitions()[0]->labelFor('en', 'en'));
        static::assertSame(3, $restored->categoryDefinitions()[0]->group);
        static::assertNull($restored->categoryDefinitions()[1]->group);
    }

    public function testAConfigStoredBeforeGroupsExistedStaysValid(): void
    {
        // Arrange
        $raw = [
            'categoriesEnabled' => true,
            'tagsEnabled' => false,
            'categories' => [['id' => 0, 'labels' => ['en' => 'Greeting']]],
            'tags' => [],
        ];

        // Act
        $config = Config::fromArray($raw);

        // Assert
        static::assertSame([], $config->categoryGroupDefinitions());
        static::assertNull($config->categoryDefinitions()[0]->group);
        static::assertEquals([['group' => null, 'definitions' => $config->categoryDefinitions()]], $config->groupedDefinitions(Axis::Category));
    }

    public function testGroupedDefinitionsPutUngroupedFirstAndSkipEmptyGroups(): void
    {
        // Arrange
        $config = (new Config())
            ->setCategoryGroups([
                ['id' => 0, 'labels' => ['en' => 'Meat']],
                ['id' => 1, 'labels' => ['en' => 'Empty']],
            ])
            ->setCategories([
                ['id' => 5, 'labels' => ['en' => 'Chicken'], 'group' => 0],
                ['id' => 6, 'labels' => ['en' => 'Soup']],
                ['id' => 7, 'labels' => ['en' => 'Beef'], 'group' => 0],
            ]);

        // Act
        $grouped = $config->groupedDefinitions(Axis::Category);

        // Assert
        static::assertCount(2, $grouped);
        static::assertNull($grouped[0]['group']);
        static::assertSame([6], array_column($grouped[0]['definitions'], 'id'));
        static::assertSame('Meat', $grouped[1]['group']?->labelFor('en', 'en'));
        static::assertSame([5, 7], array_column($grouped[1]['definitions'], 'id'));
    }

    public function testGroupedCategoryOptionsNestGroupedEntriesUnderTheirLabel(): void
    {
        // Arrange
        $config = (new Config())
            ->setCategoryGroups([['id' => 0, 'labels' => ['en' => 'Meat']]])
            ->setCategories([
                ['id' => 5, 'labels' => ['en' => 'Chicken'], 'group' => 0],
                ['id' => 6, 'labels' => ['en' => 'Soup']],
            ]);

        // Act
        $options = $config->groupedCategoryOptions('en', 'en');

        // Assert
        static::assertSame(['Soup' => 6, 'Meat' => ['Chicken' => 5]], $options);
    }

    public function testGroupedOptionsStayFlatWithoutGroups(): void
    {
        // Arrange
        $config = (new Config())->setCategories([
            ['id' => 3, 'labels' => ['en' => 'Slang']],
            ['id' => 7, 'labels' => ['en' => 'Idioms']],
        ]);

        // Act + Assert
        static::assertSame($config->categoryOptions('en', 'en'), $config->groupedCategoryOptions('en', 'en'));
    }

    public function testTagDepthDefaultsToFlatAndIsClamped(): void
    {
        // Arrange
        $config = new Config();

        // Act + Assert
        static::assertSame(1, $config->getTagDepth());
        static::assertSame(1, $config->setTagDepth(0)->getTagDepth());
        static::assertSame(1, $config->setTagDepth(null)->getTagDepth());
        static::assertSame(Config::MAX_TAG_DEPTH, $config->setTagDepth(99)->getTagDepth());
        static::assertSame(3, $config->setTagDepth(3)->getTagDepth());
    }

    public function testNormalizeKeepsAParentWithinTheDepthLimit(): void
    {
        // Arrange
        $config = (new Config())->setTagDepth(2)->setTags([
            ['id' => 1, 'labels' => ['en' => 'Meat']],
            ['id' => 4, 'labels' => ['en' => 'Chicken'], 'parent' => 1],
        ]);

        // Act
        $config->normalize();

        // Assert
        static::assertNull($config->tagDefinitions()[0]->parent);
        static::assertSame(1, $config->tagDefinitions()[1]->parent);
        static::assertSame(2, $config->tagTree()->depthOf(4));
        static::assertSame([1], $config->tagTree()->ancestors(4));
    }

    public function testNormalizeClampsARowBeyondTheDepthLimitToRoot(): void
    {
        // Arrange
        $config = (new Config())->setTagDepth(2)->setTags([
            ['id' => 1, 'labels' => ['en' => 'Meat']],
            ['id' => 4, 'labels' => ['en' => 'Chicken'], 'parent' => 1],
            ['id' => 5, 'labels' => ['en' => 'Wing'], 'parent' => 4],
        ]);

        // Act
        $config->normalize();

        // Assert
        static::assertNull($config->tagDefinitions()[2]->parent);
        static::assertSame(1, $config->tagTree()->depthOf(5));
    }

    public function testADepthOfOneFlattensEveryTag(): void
    {
        // Arrange
        $config = (new Config())->setTags([
            ['id' => 1, 'labels' => ['en' => 'Meat']],
            ['id' => 4, 'labels' => ['en' => 'Chicken'], 'parent' => 1],
        ]);

        // Act
        $config->normalize();

        // Assert
        static::assertNull($config->tagDefinitions()[1]->parent);
        static::assertSame([1, 4], array_column($config->tagTree()->ordered(), 'id'));
    }

    public function testNormalizeBreaksACycleAndDropsAParentThatIsGone(): void
    {
        // Arrange
        $config = (new Config())->setTagDepth(4)->setTags([
            ['id' => 1, 'labels' => ['en' => 'Meat'], 'parent' => 2],
            ['id' => 2, 'labels' => ['en' => 'Poultry'], 'parent' => 1],
            ['id' => 3, 'labels' => ['en' => 'Orphan'], 'parent' => 99],
            ['id' => 4, 'labels' => ['en' => 'Selfish'], 'parent' => 4],
        ]);

        // Act
        $config->normalize();

        // Assert
        static::assertNull($config->tagDefinitions()[0]->parent);
        static::assertSame(1, $config->tagDefinitions()[1]->parent);
        static::assertNull($config->tagDefinitions()[2]->parent);
        static::assertNull($config->tagDefinitions()[3]->parent);
    }

    public function testNormalizeResolvesAClientTokenIntoTheAllocatedParentId(): void
    {
        // Arrange
        $config = (new Config())->setTagDepth(2)->setTags([
            ['id' => 'n1', 'labels' => ['en' => 'Meat']],
            ['id' => '', 'labels' => ['en' => 'Chicken'], 'parent' => 'n1'],
        ]);

        // Act
        $config->normalize();

        // Assert
        static::assertSame([0, 1], array_column($config->getTags(), 'id'));
        static::assertSame(0, $config->tagDefinitions()[1]->parent);
    }

    public function testRemovingAParentPromotesItsChildrenToRoot(): void
    {
        // Arrange
        $config = (new Config())->setTagDepth(2)->setTags([
            ['id' => 1, 'labels' => ['en' => 'Meat']],
            ['id' => 4, 'labels' => ['en' => 'Chicken'], 'parent' => 1],
        ]);

        // Act
        $config->removeDefinition(Axis::Tag, 1)->normalize();

        // Assert
        static::assertNull($config->tagDefinitions()[0]->parent);
    }

    public function testTagTreePutsEveryTagDirectlyAfterItsParent(): void
    {
        // Arrange
        $config = (new Config())->setTagDepth(3)->setTags([
            ['id' => 1, 'labels' => ['en' => 'Meat']],
            ['id' => 2, 'labels' => ['en' => 'Fish']],
            ['id' => 3, 'labels' => ['en' => 'Chicken'], 'parent' => 1],
            ['id' => 4, 'labels' => ['en' => 'Wing'], 'parent' => 3],
            ['id' => 5, 'labels' => ['en' => 'Pork'], 'parent' => 1],
        ]);

        // Act
        $config->normalize();

        // Assert
        static::assertSame([1, 3, 4, 5, 2], array_column($config->tagTree()->ordered(), 'id'));
        static::assertSame([3, 5], array_column($config->tagTree()->children(1), 'id'));
        static::assertSame([3, 1], $config->tagTree()->ancestors(4));
    }

    public function testTheTagAxisIsOneUngroupedBucketInTreeOrder(): void
    {
        // Arrange
        $config = (new Config())
            ->setTagDepth(2)
            ->setTags([
                ['id' => 1, 'labels' => ['en' => 'Meat']],
                ['id' => 5, 'labels' => ['en' => 'Spicy']],
                ['id' => 4, 'labels' => ['en' => 'Chicken'], 'parent' => 1],
            ]);
        $config->normalize();

        // Act
        $grouped = $config->groupedDefinitions(Axis::Tag);

        // Assert
        static::assertCount(1, $grouped);
        static::assertNull($grouped[0]['group']);
        static::assertSame([1, 4, 5], array_column($grouped[0]['definitions'], 'id'));
        static::assertSame(['Meat' => 1, 'Chicken' => 4, 'Spicy' => 5], $config->tagOptions('en', 'en'));
    }

    public function testToArrayFromArrayRoundTripsTheTree(): void
    {
        // Arrange
        $config = (new Config())->setTagDepth(3)->setTags([
            ['id' => 1, 'labels' => ['en' => 'Meat']],
            ['id' => 4, 'labels' => ['en' => 'Chicken'], 'parent' => 1],
        ]);

        // Act
        $raw = $config->toArray();
        $restored = Config::fromArray($raw);

        // Assert
        static::assertSame(3, $raw['tagDepth']);
        static::assertArrayNotHasKey('parent', $raw['tags'][0]);
        static::assertSame(1, $raw['tags'][1]['parent']);
        static::assertSame(3, $restored->getTagDepth());
        static::assertSame(1, $restored->tagDefinitions()[1]->parent);
    }

    public function testAConfigStoredBeforeTheTreeExistedStaysFlat(): void
    {
        // Arrange
        $raw = ['tagsEnabled' => true, 'tags' => [['id' => 0, 'labels' => ['en' => 'Spicy']]]];

        // Act
        $config = Config::fromArray($raw);

        // Assert
        static::assertSame(1, $config->getTagDepth());
        static::assertFalse($config->tagTree()->hasBranches());
        static::assertNull($config->tagDefinitions()[0]->parent);
    }

    #[DataProvider('labelFallbackCases')]
    public function testCategoryDefinitionLabelForFallbackChain(array $labels, ?string $locale, string $source, string $expected): void
    {
        // Arrange
        $definition = new CategoryDefinition(1, $labels);

        // Act
        $label = $definition->labelFor($locale, $source);

        // Assert
        static::assertSame($expected, $label);
    }

    public static function labelFallbackCases(): iterable
    {
        yield 'requested locale wins' => [['en' => 'Hello', 'de' => 'Hallo'], 'de', 'en', 'Hallo'];
        yield 'falls back to source locale' => [['en' => 'Hello'], 'de', 'en', 'Hello'];
        yield 'falls back to first non-empty' => [['de' => 'Hallo'], 'fr', 'en', 'Hallo'];
        yield 'blank requested skips to source' => [['fr' => '', 'en' => 'Hello'], 'fr', 'en', 'Hello'];
        yield 'all blank yields empty string' => [['en' => '', 'de' => ''], 'en', 'en', ''];
    }
}
