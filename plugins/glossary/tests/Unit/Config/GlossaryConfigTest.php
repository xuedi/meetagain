<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\Config;

use PHPUnit\Framework\TestCase;
use Plugin\Glossary\Config\GlossaryConfig;

class GlossaryConfigTest extends TestCase
{
    public function testNeutralDefaultIsTermAndDefinitionOnly(): void
    {
        // Arrange + Act
        $config = new GlossaryConfig();

        // Assert
        static::assertFalse($config->isSecondaryEnabled());
        static::assertFalse($config->hasCategories());
        static::assertNull($config->getPrimaryLabel());
        static::assertNull($config->getSecondaryLabel());
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        // Arrange
        $config = (new GlossaryConfig())
            ->setSecondaryEnabled(true)
            ->setSecondaryLabel('Pinyin')
            ->setPrimaryLabel('Word')
            ->setDefinitionLabel('Meaning')
            ->setCategories([['id' => 0, 'label' => 'Greeting'], ['id' => 1, 'label' => 'Slang']]);

        // Act
        $restored = GlossaryConfig::fromArray($config->toArray());

        // Assert
        static::assertTrue($restored->isSecondaryEnabled());
        static::assertSame('Pinyin', $restored->getSecondaryLabel());
        static::assertSame('Word', $restored->getPrimaryLabel());
        static::assertSame('Meaning', $restored->getDefinitionLabel());
        static::assertSame([0 => 'Greeting', 1 => 'Slang'], $restored->getCategoryMap());
    }

    public function testFromArrayCoercesCategoryIdsToInt(): void
    {
        // Arrange
        $raw = ['categories' => [['id' => '4', 'label' => 'Abbreviation']]];

        // Act
        $config = GlossaryConfig::fromArray($raw);

        // Assert
        static::assertSame('Abbreviation', $config->getCategoryLabel(4));
    }

    public function testNormalizeAssignsIdsAndDropsEmptyLabels(): void
    {
        // Arrange
        $config = (new GlossaryConfig())->setCategories([
            ['id' => 5, 'label' => 'Existing'],
            ['id' => '', 'label' => 'Fresh'],
            ['id' => '', 'label' => '   '],
        ]);

        // Act
        $config->normalizeCategories();

        // Assert: existing id preserved, new row gets max+1, blank-label row dropped
        static::assertSame([5 => 'Existing', 6 => 'Fresh'], $config->getCategoryMap());
    }

    public function testGetCategoryLabelReturnsNullForUnknownId(): void
    {
        // Arrange
        $config = (new GlossaryConfig())->setCategories([['id' => 0, 'label' => 'Greeting']]);

        // Act + Assert
        static::assertNull($config->getCategoryLabel(99));
        static::assertNull($config->getCategoryLabel(null));
    }
}
