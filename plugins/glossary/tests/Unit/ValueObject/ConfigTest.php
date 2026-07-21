<?php declare(strict_types=1);

namespace Plugin\Glossary\Tests\Unit\ValueObject;

use App\Item\Taxonomy\TaxonomyConfig;
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
        $taxonomy = (new TaxonomyConfig())
            ->setCategoriesEnabled(true)
            ->setCategories([['id' => 0, 'labels' => ['en' => 'Greeting']], ['id' => 1, 'labels' => ['en' => 'Slang']]]);
        $config = (new Config())
            ->setSecondaryEnabled(true)
            ->setSecondaryLabel('Pinyin')
            ->setPrimaryLabel('Word')
            ->setDefinitionLabel('Meaning')
            ->setTaxonomy($taxonomy);

        // Act
        $restored = Config::fromArray($config->toArray());

        // Assert
        static::assertTrue($restored->isSecondaryEnabled());
        static::assertSame('Pinyin', $restored->getSecondaryLabel());
        static::assertSame('Word', $restored->getPrimaryLabel());
        static::assertSame('Meaning', $restored->getDefinitionLabel());
        static::assertTrue($restored->hasCategories());
        static::assertSame('Greeting', $restored->getTaxonomy()->categoryLabel(0, 'en', 'en'));
        static::assertSame('Slang', $restored->getTaxonomy()->categoryLabel(1, 'en', 'en'));
    }

    public function testHasCategoriesIsFalseWhenTaxonomyDisabled(): void
    {
        // Arrange: categories defined but the enable flag is off
        $taxonomy = (new TaxonomyConfig())->setCategories([['id' => 0, 'labels' => ['en' => 'Greeting']]]);
        $config = (new Config())->setTaxonomy($taxonomy);

        // Act + Assert
        static::assertFalse($config->hasCategories());
    }
}
