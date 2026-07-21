<?php declare(strict_types=1);

namespace Tests\Unit\Item\Taxonomy;

use App\Item\Taxonomy\CategoryDefinition;
use App\Item\Taxonomy\TaxonomyConfig;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class TaxonomyConfigTest extends TestCase
{
    public function testNeutralDefaultIsDisabledAndEmpty(): void
    {
        // Arrange + Act
        $config = new TaxonomyConfig();

        // Assert
        static::assertFalse($config->isCategoriesEnabled());
        static::assertFalse($config->isTagsEnabled());
        static::assertSame([], $config->categoryDefinitions());
        static::assertSame([], $config->tagDefinitions());
    }

    public function testNormalizeAssignsIdsDropsBlankRowsAcrossBothLists(): void
    {
        // Arrange
        $config = (new TaxonomyConfig())
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

        // Assert: existing id preserved, new row gets max+1, all-blank row dropped, tags start at 0
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
        $config = (new TaxonomyConfig())
            ->setCategoriesEnabled(true)
            ->setTagsEnabled(true)
            ->setCategories([['id' => 0, 'labels' => ['en' => 'Greeting', 'de' => 'Gruss']]])
            ->setTags([['id' => 0, 'labels' => ['en' => 'Formal']]]);

        // Act
        $restored = TaxonomyConfig::fromArray($config->toArray());

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
        $config = (new TaxonomyConfig())->setCategoriesEnabled(true)->setCategories([
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
        $config = (new TaxonomyConfig())
            ->setCategories([['id' => 2, 'labels' => ['en' => 'A']]])
            ->setTags([['id' => 4, 'labels' => ['en' => 'B']]]);

        // Act + Assert
        static::assertTrue($config->hasCategory(2));
        static::assertFalse($config->hasCategory(99));
        static::assertTrue($config->hasTag(4));
        static::assertFalse($config->hasTag(99));
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
