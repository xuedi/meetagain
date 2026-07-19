<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Plugin\Glossary\ValueObject\Config;

class ConfigTest extends TestCase
{
    public function testNeutralDefaultIsTermAndDefinitionOnly(): void
    {
        // Arrange + Act
        $config = new Config();

        // Assert
        static::assertFalse($config->isSecondaryEnabled());
        static::assertFalse($config->hasCategories());
        static::assertNull($config->getPrimaryLabel());
        static::assertNull($config->getSecondaryLabel());
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        // Arrange
        $config = (new Config())
            ->setSecondaryEnabled(true)
            ->setSecondaryLabel('Pinyin')
            ->setPrimaryLabel('Word')
            ->setDefinitionLabel('Meaning')
            ->setCategories([['id' => 0, 'label' => 'Greeting'], ['id' => 1, 'label' => 'Slang']]);

        // Act
        $restored = Config::fromArray($config->toArray());

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
        $config = Config::fromArray($raw);

        // Assert
        static::assertSame('Abbreviation', $config->getCategoryLabel(4));
    }

    public function testNormalizeAssignsIdsAndDropsEmptyLabels(): void
    {
        // Arrange
        $config = (new Config())->setCategories([
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
        $config = (new Config())->setCategories([['id' => 0, 'label' => 'Greeting']]);

        // Act + Assert
        static::assertNull($config->getCategoryLabel(99));
        static::assertNull($config->getCategoryLabel(null));
    }
}
