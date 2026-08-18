<?php declare(strict_types=1);

namespace Tests\Unit\Service\Cms;

use App\Service\Cms\RichTextNormalizer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RichTextNormalizerTest extends TestCase
{
    private RichTextNormalizer $normalizer;

    protected function setUp(): void
    {
        $this->normalizer = new RichTextNormalizer();
    }

    public function testConsecutiveParagraphsJoinIntoOneSeparatedByBreaks(): void
    {
        // Arrange
        $editor = '<p>MeetAgain UG</p><p>Urbanstrasse 96</p><p>10967 Berlin</p>';

        // Act
        $stored = $this->normalizer->toStorage($editor);

        // Assert
        static::assertSame('<p>MeetAgain UG<br>Urbanstrasse 96<br>10967 Berlin</p>', $stored);
    }

    #[DataProvider('provideBlankParagraphs')]
    public function testABlankParagraphClosesTheGroupAndIsDropped(string $blank): void
    {
        // Arrange
        $editor = '<p>First</p>' . $blank . '<p>Second</p>';

        // Act
        $stored = $this->normalizer->toStorage($editor);

        // Assert
        static::assertSame('<p>First</p><p>Second</p>', $stored);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideBlankParagraphs(): iterable
    {
        yield 'empty' => ['<p></p>'];
        yield 'break only' => ['<p><br></p>'];
        yield 'space only' => ['<p> </p>'];
        yield 'nbsp only' => ['<p>&nbsp;</p>'];
    }

    #[DataProvider('provideNonParagraphBlocks')]
    public function testANonParagraphBlockClosesTheGroupAndPassesThroughUntouched(string $block): void
    {
        // Arrange
        $editor = '<p>Before</p>' . $block . '<p>After</p>';

        // Act
        $stored = $this->normalizer->toStorage($editor);

        // Assert
        static::assertSame('<p>Before</p>' . $block . '<p>After</p>', $stored);
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideNonParagraphBlocks(): iterable
    {
        yield 'headline' => ['<h2>Editorial Responsibility</h2>'];
        yield 'unordered list' => ['<ul><li>one</li><li>two</li></ul>'];
        yield 'ordered list' => ['<ol><li>one</li></ol>'];
        yield 'blockquote' => ['<blockquote>quoted</blockquote>'];
        yield 'table' => ['<table><tbody><tr><td>cell</td></tr></tbody></table>'];
    }

    public function testLeadingAndTrailingWhitespaceIsTrimmedFromEachLine(): void
    {
        // Arrange
        $editor = '<p> MeetAgain UG</p><p>  Urbanstrasse 96  </p>';

        // Act
        $stored = $this->normalizer->toStorage($editor);

        // Assert
        static::assertSame('<p>MeetAgain UG<br>Urbanstrasse 96</p>', $stored);
    }

    public function testTrailingBlankParagraphsAreDropped(): void
    {
        // Arrange
        $editor = '<p>Only line</p><p></p><p><br></p>';

        // Act
        $stored = $this->normalizer->toStorage($editor);

        // Assert
        static::assertSame('<p>Only line</p>', $stored);
    }

    public function testInlineMarkupInsideALineSurvives(): void
    {
        // Arrange
        $editor = '<p><strong>Chairman: </strong></p><p>Daniel Koch</p>';

        // Act
        $stored = $this->normalizer->toStorage($editor);

        // Assert
        static::assertSame('<p><strong>Chairman: </strong><br>Daniel Koch</p>', $stored);
    }

    #[DataProvider('provideEmptyInput')]
    public function testEmptyInputYieldsAnEmptyString(string $input): void
    {
        // Act & Assert
        static::assertSame('', $this->normalizer->toStorage($input));
        static::assertSame('', $this->normalizer->toEditor($input));
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideEmptyInput(): iterable
    {
        yield 'empty string' => [''];
        yield 'whitespace' => ['   '];
        yield 'single blank paragraph' => ['<p></p>'];
        yield 'quill empty document' => ['<p><br></p>'];
    }

    public function testToEditorSplitsBreaksIntoLinesAndSeparatesParagraphsWithABlankLine(): void
    {
        // Arrange
        $stored = '<p>Daniel Koch<br>Urbanstrasse 96</p><p>Second group</p>';

        // Act
        $editor = $this->normalizer->toEditor($stored);

        // Assert
        static::assertSame('<p>Daniel Koch</p><p>Urbanstrasse 96</p><p></p><p>Second group</p>', $editor);
    }

    public function testToEditorDoesNotInsertABlankLineAcrossANonParagraphBlock(): void
    {
        // Arrange
        $stored = '<p>Before</p><h2>Heading</h2><p>After</p>';

        // Act
        $editor = $this->normalizer->toEditor($stored);

        // Assert
        static::assertSame('<p>Before</p><h2>Heading</h2><p>After</p>', $editor);
    }

    public function testToStorageConsumesEveryEditorParagraphMarker(): void
    {
        // Act
        $stored = $this->normalizer->toStorage(self::brokenImprint());

        // Assert
        static::assertFalse(
            $this->normalizer->containsBlankParagraph($stored),
            'stored output must no longer look like editor output, or the backfill would re-run on it',
        );
    }

    public function testTwoAdjacentSingleLineParagraphsAreJoined(): void
    {
        // Act
        $stored = $this->normalizer->toStorage('<p>One</p><p>Two</p>');

        // Assert
        static::assertSame(
            '<p>One<br>Two</p>',
            $stored,
            'toStorage reads adjacency as one paragraph - its input language is editor output, not storage',
        );
    }

    #[DataProvider('provideBlankParagraphMarkerCases')]
    public function testContainsBlankParagraphDetectsEditorOutput(string $html, bool $expected): void
    {
        // Act & Assert
        static::assertSame($expected, $this->normalizer->containsBlankParagraph($html));
    }

    /**
     * @return iterable<string, array{0: string, 1: bool}>
     */
    public static function provideBlankParagraphMarkerCases(): iterable
    {
        yield 'flattened imprint' => [self::brokenImprint(), true];
        yield 'repaired imprint' => [self::repairedImprint(), false];
        yield 'empty paragraph marker' => ['<p>One</p><p></p><p>Two</p>', true];
        yield 'break paragraph marker' => ['<p>One</p><p><br></p><p>Two</p>', true];
        yield 'adjacent single lines' => ['<p>One</p><p>Two</p>', false];
        yield 'stored multiline' => ['<p>One<br>Two</p>', false];
        yield 'empty document' => ['', false];
    }

    public function testToStorageLeavesAlreadyStoredMarkupUntouched(): void
    {
        // Arrange
        $stored = self::repairedImprint();

        // Act
        $result = $this->normalizer->toStorage($stored);

        // Assert
        static::assertSame($stored, $result, 'a re-run over stored data must not merge its paragraphs');
    }

    public function testToStorageRepairsTheFlattenedImprintBlock(): void
    {
        // Act
        $stored = $this->normalizer->toStorage(self::brokenImprint());

        // Assert
        static::assertSame(self::repairedImprint(), $stored);
    }

    #[DataProvider('provideRoundTripDocuments')]
    public function testStoredMarkupSurvivesAFullEditorRoundTrip(string $stored): void
    {
        // Act
        $once = $this->normalizer->toStorage(self::asMeasuredQuillMountAndSerializeWouldReturnIt($this->normalizer->toEditor($stored)));
        $twice = $this->normalizer->toStorage(self::asMeasuredQuillMountAndSerializeWouldReturnIt($this->normalizer->toEditor($once)));

        // Assert
        static::assertSame($stored, $once);
        static::assertSame($stored, $twice, 'the round trip stays stable on a second pass');
    }

    /**
     * @return iterable<string, array{0: string}>
     */
    public static function provideRoundTripDocuments(): iterable
    {
        yield 'address block' => ['<p>Daniel Koch<br>Urbanstrasse 96<br>10967 Berlin<br>Germany</p>'];
        yield 'multibyte address block' => ['<p>Daniel Koch<br>Urbanstrasse 96<br>10967 柏林<br>德国</p>'];
        yield 'two groups' => ['<p>One<br>Two</p><p>Three</p>'];
        yield 'headline between groups' => ['<p>Intro</p><h2>Heading</h2><p>Body<br>Second line</p>'];
        yield 'list between groups' => ['<p>Intro</p><ul><li>one</li></ul><p>Body</p>'];
        yield 'inline markup' => ['<p><strong>Chairman: </strong><br>Daniel Koch</p>'];
        yield 'repaired imprint' => [self::repairedImprint()];
    }

    private static function asMeasuredQuillMountAndSerializeWouldReturnIt(string $editorHtml): string
    {
        return str_replace('<p><br></p>', '<p></p>', $editorHtml);
    }

    private static function brokenImprint(): string
    {
        return '<p><strong>Information pursuant to §5 DDG</strong></p><p></p>'
            . '<p> MeetAgain UG</p><p> Urbanstrasse 96</p><p> 10967 Berlin</p><p> Germany</p><p></p>'
            . '<h2>Editorial Responsibility</h2>'
            . '<p>Responsible for editorial content:</p><p>Daniel Koch, Urbanstrasse 96</p>';
    }

    private static function repairedImprint(): string
    {
        return '<p><strong>Information pursuant to §5 DDG</strong></p>'
            . '<p>MeetAgain UG<br>Urbanstrasse 96<br>10967 Berlin<br>Germany</p>'
            . '<h2>Editorial Responsibility</h2>'
            . '<p>Responsible for editorial content:<br>Daniel Koch, Urbanstrasse 96</p>';
    }
}
