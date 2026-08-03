<?php declare(strict_types=1);

namespace Tests\Unit\Calendar;

use App\Calendar\Entry;
use App\Calendar\Writer;
use DateTimeImmutable;
use DateTimeZone;
use Generator;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class WriterTest extends TestCase
{
    public function testRendersCalendarEnvelopeAndEvent(): void
    {
        // Arrange
        $writer = new Writer();

        // Act
        $document = $writer->write([$this->entry()], 'example.org - Upcoming events', $this->stamp());

        // Assert
        self::assertStringStartsWith("BEGIN:VCALENDAR\r\n", $document);
        self::assertStringEndsWith("END:VCALENDAR\r\n", $document);
        self::assertStringContainsString("VERSION:2.0\r\n", $document);
        self::assertStringContainsString("METHOD:PUBLISH\r\n", $document);
        self::assertStringContainsString("UID:event-7@example.org\r\n", $document);
        self::assertStringContainsString("DTSTAMP:20260803T100000Z\r\n", $document);
        self::assertStringContainsString("DTSTART:20260901T180000Z\r\n", $document);
        self::assertStringContainsString("DTEND:20260901T200000Z\r\n", $document);
        self::assertStringContainsString("SUMMARY:Salsa night\r\n", $document);
        self::assertStringContainsString("STATUS:CONFIRMED\r\n", $document);
    }

    public function testEveryLineTerminatesWithCarriageReturnLineFeed(): void
    {
        // Arrange
        $writer = new Writer();

        // Act
        $document = $writer->write([$this->entry()], 'calendar', $this->stamp());

        // Assert
        self::assertSame(substr_count($document, "\n"), substr_count($document, "\r\n"));
    }

    public function testCanceledEntryCarriesCanceledStatus(): void
    {
        // Arrange
        $writer = new Writer();

        // Act
        $document = $writer->write([$this->entry(cancelled: true)], 'calendar', $this->stamp());

        // Assert
        self::assertStringContainsString("STATUS:CANCELLED\r\n", $document);
    }

    public function testOmitsEmptyDescriptionAndLocation(): void
    {
        // Arrange
        $writer = new Writer();

        // Act
        $document = $writer->write([$this->entry(description: '', location: null)], 'calendar', $this->stamp());

        // Assert
        self::assertStringNotContainsString('DESCRIPTION:', $document);
        self::assertStringNotContainsString('LOCATION:', $document);
    }

    public function testFoldsEveryLineToSeventyFiveOctets(): void
    {
        // Arrange
        $writer = new Writer();

        // Act
        $document = $writer->write([$this->entry(summary: str_repeat('a', 400))], 'calendar', $this->stamp());

        // Assert
        foreach (explode("\r\n", rtrim($document, "\r\n")) as $line) {
            self::assertLessThanOrEqual(75, strlen($line), sprintf('Line exceeds 75 octets: %s', $line));
        }
    }

    public function testFoldedContinuationLinesStartWithASpace(): void
    {
        // Arrange
        $writer = new Writer();

        // Act
        $document = $writer->write([$this->entry(summary: str_repeat('a', 200))], 'calendar', $this->stamp());

        // Assert
        self::assertStringContainsString("\r\n a", $document);
    }

    public function testFoldNeverSplitsAMultibyteCharacter(): void
    {
        // Arrange
        $writer = new Writer();
        $summary = str_repeat('äöü', 60);

        // Act
        $document = $writer->write([$this->entry(summary: $summary)], 'calendar', $this->stamp());

        // Assert
        foreach (explode("\r\n", rtrim($document, "\r\n")) as $line) {
            self::assertTrue(mb_check_encoding($line, 'UTF-8'), sprintf('Line is not valid UTF-8: %s', bin2hex($line)));
        }

        self::assertStringContainsString('SUMMARY:' . $summary, str_replace("\r\n ", '', $document));
    }

    #[DataProvider('textEscapingProvider')]
    public function testEscapesTextValues(string $summary, string $expected): void
    {
        // Arrange
        $writer = new Writer();

        // Act
        $document = $writer->write([$this->entry(summary: $summary)], 'calendar', $this->stamp());

        // Assert
        self::assertStringContainsString('SUMMARY:' . $expected, str_replace("\r\n ", '', $document));
    }

    /** @return Generator<string, array{string, string}> */
    public static function textEscapingProvider(): Generator
    {
        yield 'semicolon is escaped' => ['before;after', 'before\;after'];
        yield 'comma is escaped' => ['before,after', 'before\,after'];
        yield 'backslash is doubled' => ['before\\after', 'before\\\\after'];
        yield 'newline becomes a literal n' => ["before\nafter", 'before\nafter'];
        yield 'carriage return newline becomes one literal n' => ["before\r\nafter", 'before\nafter'];
        yield 'colon is left alone' => ['before:after', 'before:after'];
    }

    private function entry(
        string $summary = 'Salsa night',
        string $description = 'Come dance with us',
        ?string $location = 'Studio, Main Street 1, 10115 Berlin',
        bool $cancelled = false,
    ): Entry {
        return new Entry(
            uid: 'event-7@example.org',
            summary: $summary,
            description: $description,
            url: 'https://example.org/en/event/7',
            start: new DateTimeImmutable('2026-09-01 18:00:00', new DateTimeZone('UTC')),
            end: new DateTimeImmutable('2026-09-01 20:00:00', new DateTimeZone('UTC')),
            location: $location,
            cancelled: $cancelled,
        );
    }

    private function stamp(): DateTimeImmutable
    {
        return new DateTimeImmutable('2026-08-03 10:00:00', new DateTimeZone('UTC'));
    }
}
