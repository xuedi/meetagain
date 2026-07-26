<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Service\Media\ImageTypes\ImageTypeDefinitionInterface;
use App\Service\Media\ThumbnailSizeFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ThumbnailSizeFormatTest extends TestCase
{
    /**
     * @param array{0: int, 1: int} $size
     */
    #[DataProvider('provideRoundTripCases')]
    public function testSizeAndTokenRoundTripInBothDirections(array $size, string $expected): void
    {
        // Arrange
        $subject = new ThumbnailSizeFormat();

        // Act
        $formatted = $subject->format($size[0], $size[1]);
        $parsed = $subject->parse($expected);

        // Assert
        static::assertSame($expected, $formatted);
        static::assertSame($size, $parsed);
    }

    public static function provideRoundTripCases(): iterable
    {
        $free = ImageTypeDefinitionInterface::FREE_AXIS;

        yield 'fixed box' => [[840, 120], '840x120'];
        yield 'square box' => [[50, 50], '50x50'];
        yield 'free width, fixed height' => [[$free, 120], 'h120'];
        yield 'fixed width, free height' => [[350, $free], 'w350'];
    }

    #[DataProvider('provideUnparseableTokens')]
    public function testParseRejectsAnythingThatIsNotASizeToken(string $size): void
    {
        // Arrange
        $subject = new ThumbnailSizeFormat();

        // Act
        $result = $subject->parse($size);

        // Assert
        static::assertNull($result);
    }

    public static function provideUnparseableTokens(): iterable
    {
        yield 'empty' => [''];
        yield 'prose' => ['thumbnail'];
        yield 'free axis marker without a fixed axis' => ['h'];
        yield 'both axes free' => ['hw'];
        yield 'negative literal' => ['-1x120'];
        yield 'three axes' => ['10x20x30'];
        yield 'trailing junk' => ['h120px'];
    }
}
