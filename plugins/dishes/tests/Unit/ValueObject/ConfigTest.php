<?php declare(strict_types=1);

namespace Plugin\Dishes\Tests\Unit\ValueObject;

use PHPUnit\Framework\TestCase;
use Plugin\Dishes\ValueObject\Config;

class ConfigTest extends TestCase
{
    public function testNeutralDefaultHasNoFooterAndNoPhonetic(): void
    {
        // Arrange + Act
        $config = new Config();

        // Assert
        static::assertSame([], $config->getFooterText());
        static::assertSame('', $config->getFooterFor('en'));
        static::assertFalse($config->isPhoneticInList());
    }

    public function testToArrayFromArrayRoundTrip(): void
    {
        // Arrange
        $config = (new Config())
            ->setFooterText(['en' => 'See you next time', 'zh' => '下次见'])
            ->setPhoneticInList(true);

        // Act
        $restored = Config::fromArray($config->toArray());

        // Assert
        static::assertSame('See you next time', $restored->getFooterFor('en'));
        static::assertSame('下次见', $restored->getFooterFor('zh'));
        static::assertTrue($restored->isPhoneticInList());
    }

    public function testPhoneticDefaultsToFalseWhenAbsentFromArray(): void
    {
        // Arrange + Act
        $restored = Config::fromArray(['footerText' => ['en' => 'Hi']]);

        // Assert
        static::assertFalse($restored->isPhoneticInList());
    }

    public function testSetFooterTextDropsEmptyAndTrims(): void
    {
        // Arrange + Act
        $config = (new Config())->setFooterText(['en' => '  Hello  ', 'de' => '   ', 'zh' => '']);

        // Assert
        static::assertSame(['en' => 'Hello'], $config->getFooterText());
    }

    public function testGetFooterForReturnsEmptyStringForMissingLocale(): void
    {
        // Arrange
        $config = (new Config())->setFooterText(['en' => 'Hi']);

        // Act + Assert
        static::assertSame('', $config->getFooterFor('de'));
    }
}
