<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Migration;

use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Migration\LegacyGlossaryCategoryConverter;

class LegacyGlossaryCategoryConverterTest extends TestCase
{
    public function testConvertsSingleLabelCategoriesToPerLocaleTaxonomy(): void
    {
        // Arrange
        $old = [
            'secondaryEnabled' => true,
            'secondaryLabel' => 'Pinyin',
            'categories' => [
                ['id' => 0, 'label' => 'Greeting'],
                ['id' => 1, 'label' => 'Swearing'],
            ],
        ];

        // Act
        $new = LegacyGlossaryCategoryConverter::convert($old, 'en');

        // Assert
        static::assertNotNull($new);
        static::assertArrayNotHasKey('categories', $new);
        static::assertTrue($new['taxonomy']['categoriesEnabled']);
        static::assertFalse($new['taxonomy']['tagsEnabled']);
        static::assertSame(['en' => 'Greeting'], $new['taxonomy']['categories'][0]['labels']);
        static::assertSame(0, $new['taxonomy']['categories'][0]['id']);
        static::assertSame('Pinyin', $new['secondaryLabel']);
    }

    public function testReturnsNullWhenAlreadyMigrated(): void
    {
        // Arrange: a config already carrying a taxonomy key
        $already = ['taxonomy' => ['categoriesEnabled' => true, 'categories' => []]];

        // Act + Assert
        static::assertNull(LegacyGlossaryCategoryConverter::convert($already, 'en'));
    }

    public function testReturnsNullWhenNoCategoriesKey(): void
    {
        // Arrange
        $noCategories = ['secondaryEnabled' => false];

        // Act + Assert
        static::assertNull(LegacyGlossaryCategoryConverter::convert($noCategories, 'en'));
    }

    public function testDisablesCategoriesWhenOldListWasEmpty(): void
    {
        // Arrange
        $old = ['categories' => []];

        // Act
        $new = LegacyGlossaryCategoryConverter::convert($old, 'en');

        // Assert
        static::assertNotNull($new);
        static::assertFalse($new['taxonomy']['categoriesEnabled']);
        static::assertSame([], $new['taxonomy']['categories']);
    }
}
