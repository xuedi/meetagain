<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Twig\TextDiffExtension;
use PHPUnit\Framework\TestCase;

class TextDiffExtensionTest extends TestCase
{
    public function testAnAppendedSuffixIsMarkedOnlyInTheAfterSide(): void
    {
        // Arrange
        $extension = new TextDiffExtension();

        // Act
        $diff = $extension->textDiff('Fototour - Portraits bei vorhandenem Licht', 'Fototour - Portraits bei vorhandenem Licht 2');

        // Assert
        static::assertTrue($diff['marked']);
        static::assertSame([['text' => 'Fototour - Portraits bei vorhandenem Licht', 'changed' => false]], $diff['before']);
        static::assertSame(
            [
                ['text' => 'Fototour - Portraits bei vorhandenem Licht', 'changed' => false],
                ['text' => ' 2', 'changed' => true],
            ],
            $diff['after'],
        );
    }

    public function testASimilarReplacedWordIsRefinedToCharacters(): void
    {
        // Arrange
        $extension = new TextDiffExtension();

        // Act
        $diff = $extension->textDiff('Portraits bei Licht', 'Porträts bei Licht');

        // Assert
        static::assertTrue($diff['marked']);
        static::assertSame(
            [
                ['text' => 'Portr', 'changed' => false],
                ['text' => 'ai', 'changed' => true],
                ['text' => 'ts bei Licht', 'changed' => false],
            ],
            $diff['before'],
        );
        static::assertSame(
            [
                ['text' => 'Portr', 'changed' => false],
                ['text' => 'ä', 'changed' => true],
                ['text' => 'ts bei Licht', 'changed' => false],
            ],
            $diff['after'],
        );
    }

    public function testADifferentWordIsMarkedWhole(): void
    {
        // Arrange
        $extension = new TextDiffExtension();

        // Act
        $diff = $extension->textDiff('Meet at the old station today', 'Meet at the new harbour today');

        // Assert
        static::assertTrue($diff['marked']);
        static::assertSame(
            [
                ['text' => 'Meet at the ', 'changed' => false],
                ['text' => 'old station', 'changed' => true],
                ['text' => ' today', 'changed' => false],
            ],
            $diff['before'],
        );
        static::assertSame(
            [
                ['text' => 'Meet at the ', 'changed' => false],
                ['text' => 'new harbour', 'changed' => true],
                ['text' => ' today', 'changed' => false],
            ],
            $diff['after'],
        );
    }

    public function testEachHanCharacterIsItsOwnToken(): void
    {
        // Arrange
        $extension = new TextDiffExtension();

        // Act
        $diff = $extension->textDiff('你好朋友', '你好老朋友');

        // Assert
        static::assertTrue($diff['marked']);
        static::assertSame([['text' => '你好朋友', 'changed' => false]], $diff['before']);
        static::assertSame(
            [
                ['text' => '你好', 'changed' => false],
                ['text' => '老', 'changed' => true],
                ['text' => '朋友', 'changed' => false],
            ],
            $diff['after'],
        );
    }

    public function testLineBreaksSurviveInTheSegments(): void
    {
        // Arrange
        $extension = new TextDiffExtension();

        // Act
        $diff = $extension->textDiff("First line\nSecond line", "First line\nSecond line\nThird line");

        // Assert
        static::assertTrue($diff['marked']);
        static::assertSame("\nThird line", $diff['after'][1]['text']);
        static::assertTrue($diff['after'][1]['changed']);
    }

    public function testAnEmptySideIsReturnedWithoutSegments(): void
    {
        // Arrange
        $extension = new TextDiffExtension();

        // Act
        $added = $extension->textDiff(null, 'nĭ hăo');
        $cleared = $extension->textDiff('nĭ hăo', '');

        // Assert
        static::assertSame(['marked' => false, 'before' => [], 'after' => [['text' => 'nĭ hăo', 'changed' => false]]], $added);
        static::assertSame(['marked' => false, 'before' => [['text' => 'nĭ hăo', 'changed' => false]], 'after' => []], $cleared);
    }

    public function testIdenticalTextIsNotMarked(): void
    {
        // Arrange
        $extension = new TextDiffExtension();

        // Act
        $diff = $extension->textDiff('same', 'same');

        // Assert
        static::assertFalse($diff['marked']);
    }

    public function testTooManyTokensFallBackToPlain(): void
    {
        // Arrange
        $extension = new TextDiffExtension();
        $long = str_repeat('word ', TextDiffExtension::MAX_TOKENS);

        // Act
        $diff = $extension->textDiff($long, $long . 'more');

        // Assert
        static::assertFalse($diff['marked']);
        static::assertSame([['text' => $long . 'more', 'changed' => false]], $diff['after']);
    }

    public function testTooManyChangedRunsFallBackToPlain(): void
    {
        // Arrange
        $extension = new TextDiffExtension();
        $words = range(1, TextDiffExtension::MAX_RUNS + 1);
        $before = implode(' ', array_map(static fn(int $n): string => 'keep' . $n . ' old', $words));
        $after = implode(' ', array_map(static fn(int $n): string => 'keep' . $n . ' new', $words));

        // Act
        $diff = $extension->textDiff($before, $after);

        // Assert
        static::assertFalse($diff['marked']);
    }

    public function testARewriteWithLittleInCommonFallsBackToPlain(): void
    {
        // Arrange
        $extension = new TextDiffExtension();

        // Act
        $diff = $extension->textDiff('A quiet walk along the river', 'Loud concert in the city park tonight');

        // Assert
        static::assertFalse($diff['marked']);
    }

    public function testNothingInCommonFallsBackToPlain(): void
    {
        // Arrange
        $extension = new TextDiffExtension();

        // Act
        $diff = $extension->textDiff('alpha', 'omega');

        // Assert
        static::assertFalse($diff['marked']);
    }
}
