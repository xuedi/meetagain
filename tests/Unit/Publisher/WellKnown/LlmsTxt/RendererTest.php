<?php declare(strict_types=1);

namespace Tests\Unit\Publisher\WellKnown\LlmsTxt;

use App\Publisher\WellKnown\LlmsTxt\Link;
use App\Publisher\WellKnown\LlmsTxt\Renderer;
use App\Publisher\WellKnown\LlmsTxt\Section;
use PHPUnit\Framework\TestCase;

class RendererTest extends TestCase
{
    public function testRendersTitleSummaryAndSections(): void
    {
        // Arrange
        $renderer = new Renderer();
        $sections = [
            new Section('Events', [new Link('Upcoming', 'https://example.org/events')]),
            new Section('Pages', [new Link('Imprint', 'https://example.org/imprint', 'Legal notice')]),
        ];

        // Act
        $output = $renderer->render('meetAgain', 'An event platform.', $sections);

        // Assert
        self::assertSame(
            "# meetAgain\n"
            . "\n"
            . "> An event platform.\n"
            . "\n"
            . "## Events\n"
            . "\n"
            . "- [Upcoming](https://example.org/events)\n"
            . "\n"
            . "## Pages\n"
            . "\n"
            . "- [Imprint](https://example.org/imprint): Legal notice\n",
            $output,
        );
    }

    public function testEmptySectionIsOmitted(): void
    {
        // Arrange
        $renderer = new Renderer();

        // Act
        $output = $renderer->render('meetAgain', '', [new Section('Pages', [])]);

        // Assert
        self::assertSame("# meetAgain\n", $output);
    }

    public function testMultilineSummaryCollapsesToOneBlockquoteLine(): void
    {
        // Arrange
        $renderer = new Renderer();

        // Act
        $output = $renderer->render('meetAgain', "Line one.\n\nLine two.", []);

        // Assert
        self::assertSame("# meetAgain\n\n> Line one. Line two.\n", $output);
    }
}
