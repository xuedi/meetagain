<?php declare(strict_types=1);

namespace Tests\Unit\Twig;

use App\Twig\ShortenExtension;
use PHPUnit\Framework\TestCase;

class ShortenExtensionTest extends TestCase
{
    public function testShortValuesAreReturnedPlain(): void
    {
        // Arrange
        $extension = new ShortenExtension();

        // Act
        $output = $extension->shortened('short@example.test', 30);

        // Assert
        static::assertSame('short@example.test', $output);
    }

    public function testLongValuesAreWrappedWithTheFullValueInTitleAndBody(): void
    {
        // Arrange
        $extension = new ShortenExtension();
        $value = 'welcome+en+berlin-cinephile-club@berlin-cinephile-club.preview.invalid';

        // Act
        $output = $extension->shortened($value, 30);

        // Assert
        static::assertSame(
            sprintf('<span class="is-truncated" style="--truncate-ch: 30" title="%s">%s</span>', $value, $value),
            $output,
        );
    }

    public function testMarkupInTheValueIsEscapedInBothTitleAndBody(): void
    {
        // Arrange
        $extension = new ShortenExtension();
        $value = '<script>alert("x")</script> and a "quoted" tail that makes it long enough';

        // Act
        $output = $extension->shortened($value, 10);

        // Assert
        static::assertStringNotContainsString('<script>', $output);
        static::assertSame(2, substr_count($output, '&lt;script&gt;alert(&quot;x&quot;)&lt;/script&gt;'));
    }

    public function testPathologicallyLongValuesAreCappedBeforeReachingTheDom(): void
    {
        // Arrange
        $extension = new ShortenExtension();
        $value = str_repeat('a', 5000);

        // Act
        $output = $extension->shortened($value, 20);

        // Assert
        static::assertSame(2, substr_count($output, str_repeat('a', 200) . '...'));
        static::assertStringNotContainsString(str_repeat('a', 201), $output);
    }

    public function testEmptyValuesRenderNothing(): void
    {
        // Arrange
        $extension = new ShortenExtension();

        // Act & Assert
        static::assertSame('', $extension->shortened(null));
        static::assertSame('', $extension->shortened(''));
    }

    public function testWidthIsCarriedAsACustomProperty(): void
    {
        // Arrange
        $extension = new ShortenExtension();

        // Act
        $output = $extension->shortened(str_repeat('x', 100), 45);

        // Assert
        static::assertStringContainsString('style="--truncate-ch: 45"', $output);
    }
}
