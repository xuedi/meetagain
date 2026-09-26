<?php declare(strict_types=1);

namespace Plugin\Karaoke\Tests\Unit\Entity;

use PHPUnit\Framework\TestCase;
use Plugin\Karaoke\Entity\LyricLine;

class LyricLineTest extends TestCase
{
    public function testResolveTranslationTriesTheRequestedLocaleThenTheSourceThenAny(): void
    {
        // Arrange
        $line = new LyricLine();
        $line->setTranslation('de', 'Hallo');
        $line->setTranslation('en', 'Hello');

        // Act + Assert
        static::assertSame('Hallo', $line->resolveTranslation('de', 'en'));
        static::assertSame('Hello', $line->resolveTranslation('fr', 'en'));
        static::assertSame('Hallo', $line->resolveTranslation('fr', 'zh'));
    }

    public function testSettingAnEmptyTranslationRemovesThatLanguageOnly(): void
    {
        // Arrange
        $line = new LyricLine();
        $line->setTranslation('de', 'Hallo');
        $line->setTranslation('en', 'Hello');

        // Act
        $line->setTranslation('de', '');

        // Assert
        static::assertNull($line->findTranslation('de'));
        static::assertSame('Hello', $line->findTranslation('en')?->getText());
    }
}
