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
            ->setDefinitionLabel('Meaning');

        // Act
        $restored = Config::fromArray($config->toArray());

        // Assert
        static::assertTrue($restored->isSecondaryEnabled());
        static::assertSame('Pinyin', $restored->getSecondaryLabel());
        static::assertSame('Word', $restored->getPrimaryLabel());
        static::assertSame('Meaning', $restored->getDefinitionLabel());
    }
}
