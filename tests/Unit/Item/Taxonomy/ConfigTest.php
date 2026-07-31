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

    public function testGroupsAreNormalizedPerAxis(): void
    {
        // Arrange
        $config = (new Config())
            ->setCategoryGroups([['id' => '', 'labels' => ['en' => 'Meat']]])
            ->setTagGroups([['id' => '', 'labels' => ['en' => 'Heat']]])
            ->setTags([['id' => '', 'labels' => ['en' => 'Spicy'], 'group' => 0]]);

        // Act
        $config->normalize();

        // Assert
        static::assertSame(0, $config->categoryGroupDefinitions()[0]->id);
        static::assertSame(0, $config->tagGroupDefinitions()[0]->id);
        static::assertSame(0, $config->tagDefinitions()[0]->group);
        static::assertSame([], $config->categoryDefinitions());
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
