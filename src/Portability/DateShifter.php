<?php declare(strict_types=1);

namespace App\Portability;

use DateTimeImmutable;
use DateTimeInterface;
use DateTimeZone;

readonly class DateShifter
{
    private const string ATOM_PATTERN = '/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}[+-]\d{2}:\d{2}$/';

    public function mondayOf(DateTimeInterface $date): DateTimeImmutable
    {
        return new DateTimeImmutable($date->format('Y-m-d'))->modify('monday this week');
    }

    public function weeksBetween(DateTimeInterface $from, DateTimeInterface $to): int
    {
        $utc = new DateTimeZone('UTC');
        $fromMonday = new DateTimeImmutable($from->format('Y-m-d'), $utc)->modify('monday this week');
        $toMonday = new DateTimeImmutable($to->format('Y-m-d'), $utc)->modify('monday this week');

        return intdiv((int) $fromMonday->diff($toMonday)->format('%r%a'), 7);
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    public function shift(array $data, int $weeks): array
    {
        if ($weeks === 0) {
            return $data;
        }

        array_walk_recursive($data, function (mixed &$value) use ($weeks): void {
            if (is_string($value) && preg_match(self::ATOM_PATTERN, $value) === 1) {
                $value = $this->shiftValue($value, $weeks);
            }
        });

        return $data;
    }

    private function shiftValue(string $value, int $weeks): string
    {
        $wallTime = DateTimeImmutable::createFromFormat('!Y-m-d\TH:i:s', substr($value, 0, 19));
        if ($wallTime === false) {
            return $value;
        }

        return $wallTime->modify(sprintf('%+d days', $weeks * 7))->format(DateTimeInterface::ATOM);
    }
}
