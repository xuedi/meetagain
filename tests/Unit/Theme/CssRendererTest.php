<?php declare(strict_types=1);

namespace Tests\Unit\Theme;

use App\Theme\Config;
use App\Theme\CssRenderer;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class CssRendererTest extends TestCase
{
    public static function compiledPaletteProvider(): Generator
    {
        $bulma = json_decode((string) file_get_contents(dirname(__DIR__, 3) . '/assets/styles/vendor/bulma/package.json'), true);
        $fixture = sprintf('%s/bulma-%s-palettes.json', __DIR__, $bulma['version']);
        static::assertFileExists($fixture, 'The vendored Bulma changed: compile a palette fixture for this version with dart-sass');
        $fixtures = json_decode((string) file_get_contents($fixture), true);
        foreach ($fixtures as $hex => $expected) {
            yield $hex => [$hex, $expected];
        }
    }

    /**
     * @param array<string, string> $expected
     */
    #[DataProvider('compiledPaletteProvider')]
    public function testThePaletteMatchesWhatDartSassCompilesFromBulma(string $hex, array $expected): void
    {
        // Act
        $palette = new CssRenderer()->palette('c', $hex);

        // Assert
        static::assertSame($expected, $palette);
    }

    public function testAThemeEqualToTheCompiledBaselineRendersNothing(): void
    {
        // Arrange
        $baseline = Config::fromArray(['color_primary' => '#00d1b2', 'color_text_grey' => '#767676']);

        // Act
        $css = new CssRenderer()->render($baseline, $baseline);

        // Assert
        static::assertSame('', $css);
    }

    public function testOnlyTheChangedColoursAreRendered(): void
    {
        // Arrange
        $baseline = Config::fromArray(['color_primary' => '#00d1b2', 'color_link' => '#485fc7', 'color_text_grey' => '#767676']);
        $theme = $baseline->with(['color_link' => '#123456', 'color_text_grey' => '#555555']);

        // Act
        $css = new CssRenderer()->render($theme, $baseline);

        // Assert
        static::assertStringContainsString('--bulma-link-h: 210deg;', $css);
        static::assertStringContainsString('--bulma-link-invert-l: var(--bulma-link-', $css);
        static::assertStringContainsString('--theme-text-grey: #555555;', $css);
        static::assertStringNotContainsString('--bulma-primary-', $css);
    }
}
