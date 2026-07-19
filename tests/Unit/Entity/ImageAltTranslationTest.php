<?php declare(strict_types=1);

namespace App\Tests\Unit\Entity;

use App\Entity\Image;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ImageAltTranslationTest extends TestCase
{
    public function testGetAltForFallsBackToBaseWhenLocaleUnset(): void
    {
        // Arrange
        $image = new Image();
        $image->setAlt('base alt');

        // Act & Assert
        static::assertSame('base alt', $image->getAltFor('de'));
        static::assertSame('base alt', $image->getAltFor(null));
    }

    public function testGetAltForPrefersPerLocaleValueOverBase(): void
    {
        // Arrange
        $image = new Image();
        $image->setAlt('base alt');
        $image->setAltTranslation('de', 'deutscher Alt');

        // Act & Assert
        static::assertSame('deutscher Alt', $image->getAltFor('de'));
        static::assertSame('base alt', $image->getAltFor('en'));
    }

    public function testGetAltTranslationReturnsRawValueWithoutFallback(): void
    {
        // Arrange
        $image = new Image();
        $image->setAlt('base alt');
        $image->setAltTranslation('de', 'deutscher Alt');

        // Act & Assert
        static::assertSame('deutscher Alt', $image->getAltTranslation('de'));
        static::assertNull($image->getAltTranslation('zh'));
    }

    public function testSetAltTranslationUnsetsKeyWhenValueIsEmpty(): void
    {
        // Arrange
        $image = new Image();
        $image->setAltTranslation('de', 'deutscher Alt');

        // Act
        $image->setAltTranslation('de', '');

        // Assert - the map has no lingering empty entry, so getAltFor falls back to base again
        static::assertNull($image->getAltTranslation('de'));
        static::assertSame('base', $image->setAlt('base')->getAltFor('de'));
    }

    public function testSetAltTranslationUnsetsKeyWhenValueIsNull(): void
    {
        // Arrange
        $image = new Image();
        $image->setAltTranslation('de', 'deutscher Alt');

        // Act
        $image->setAltTranslation('de', null);

        // Assert
        static::assertNull($image->getAltTranslation('de'));
    }

    /**
     * @param array<string, string> $translations
     * @param list<string>          $codes
     * @param list<string>          $expected
     */
    #[DataProvider('missingAltProvider')]
    public function testMissingAltLocales(?string $base, array $translations, array $codes, string $source, array $expected): void
    {
        // Arrange
        $image = new Image();
        $image->setAlt($base);
        foreach ($translations as $code => $value) {
            $image->setAltTranslation($code, $value);
        }

        // Act
        $missing = $image->missingAltLocales($codes, $source);

        // Assert
        static::assertSame($expected, $missing);
    }

    /**
     * @return iterable<string, array{?string, array<string, string>, list<string>, string, list<string>}>
     */
    public static function missingAltProvider(): iterable
    {
        yield 'base alt only leaves non-source languages missing' => ['base alt', [], ['en', 'de', 'zh'], 'en', ['de', 'zh']];
        yield 'empty base with no map is missing everywhere' => [null, [], ['en', 'de', 'zh'], 'en', ['en', 'de', 'zh']];
        yield 'own value fills only its own language' => ['', ['de' => 'de alt'], ['en', 'de', 'zh'], 'en', ['en', 'zh']];
        yield 'every language has its own value' => ['e', ['de' => 'd', 'zh' => 'z'], ['en', 'de', 'zh'], 'en', []];
    }
}
