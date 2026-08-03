<?php declare(strict_types=1);

namespace App\Calendar;

use DateTimeImmutable;
use DateTimeZone;

final readonly class Writer
{
    private const int FOLD_OCTETS = 75;
    private const string LINE_BREAK = "\r\n";
    private const string PRODID = '-//meetAgain//Calendar//EN';

    /**
     * @param list<Entry> $entries
     */
    public function write(array $entries, string $calendarName, DateTimeImmutable $stamp): string
    {
        $lines = [
            'BEGIN:VCALENDAR',
            'VERSION:2.0',
            'PRODID:' . self::PRODID,
            'CALSCALE:GREGORIAN',
            'METHOD:PUBLISH',
            'X-WR-CALNAME:' . $this->escapeText($calendarName),
        ];

        foreach ($entries as $entry) {
            foreach ($this->renderEntry($entry, $stamp) as $line) {
                $lines[] = $line;
            }
        }

        $lines[] = 'END:VCALENDAR';

        return implode(self::LINE_BREAK, array_map($this->fold(...), $lines)) . self::LINE_BREAK;
    }

    /**
     * @return list<string>
     */
    private function renderEntry(Entry $entry, DateTimeImmutable $stamp): array
    {
        $lines = [
            'BEGIN:VEVENT',
            'UID:' . $entry->uid,
            'DTSTAMP:' . $this->formatUtc($stamp),
            'DTSTART:' . $this->formatUtc($entry->start),
            'DTEND:' . $this->formatUtc($entry->end),
            'SUMMARY:' . $this->escapeText($entry->summary),
        ];

        if ($entry->description !== '') {
            $lines[] = 'DESCRIPTION:' . $this->escapeText($entry->description);
        }

        if ($entry->location !== null && $entry->location !== '') {
            $lines[] = 'LOCATION:' . $this->escapeText($entry->location);
        }

        $lines[] = 'URL:' . $entry->url;
        $lines[] = 'STATUS:' . ($entry->cancelled ? 'CANCELLED' : 'CONFIRMED');
        $lines[] = 'END:VEVENT';

        return $lines;
    }

    private function formatUtc(DateTimeImmutable $value): string
    {
        return $value->setTimezone(new DateTimeZone('UTC'))->format('Ymd\THis\Z');
    }

    private function escapeText(string $value): string
    {
        return str_replace(
            ['\\', "\r\n", "\n", "\r", ';', ','],
            ['\\\\', '\\n', '\\n', '\\n', '\\;', '\\,'],
            $value,
        );
    }

    private function fold(string $line): string
    {
        if (strlen($line) <= self::FOLD_OCTETS) {
            return $line;
        }

        $characters = preg_split('//u', $line, -1, PREG_SPLIT_NO_EMPTY);
        if ($characters === false) {
            return $line;
        }

        $chunks = [];
        $current = '';
        $limit = self::FOLD_OCTETS;

        foreach ($characters as $character) {
            $wouldOverflow = strlen($current) + strlen($character) > $limit;
            if ($wouldOverflow) {
                $chunks[] = $current;
                $current = '';
                $limit = self::FOLD_OCTETS - 1;
            }

            $current .= $character;
        }

        $chunks[] = $current;

        return implode(self::LINE_BREAK . ' ', $chunks);
    }
}
