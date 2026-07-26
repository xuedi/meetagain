<?php declare(strict_types=1);

namespace App\Enum;

use DateTimeInterface;

enum Weekday: string
{
    case Monday = 'MO';
    case Tuesday = 'TU';
    case Wednesday = 'WE';
    case Thursday = 'TH';
    case Friday = 'FR';
    case Saturday = 'SA';
    case Sunday = 'SU';

    public static function fromDate(DateTimeInterface $date): self
    {
        return match ((int) $date->format('N')) {
            1 => self::Monday,
            2 => self::Tuesday,
            3 => self::Wednesday,
            4 => self::Thursday,
            5 => self::Friday,
            6 => self::Saturday,
            default => self::Sunday,
        };
    }

    /**
     * @param list<self> $weekdays
     *
     * @return list<self>
     */
    public static function sort(array $weekdays): array
    {
        $unique = array_values(array_unique($weekdays, SORT_REGULAR));
        usort($unique, static fn(self $a, self $b): int => $a->isoNumber() <=> $b->isoNumber());

        return $unique;
    }

    public function isoNumber(): int
    {
        return match ($this) {
            self::Monday => 1,
            self::Tuesday => 2,
            self::Wednesday => 3,
            self::Thursday => 4,
            self::Friday => 5,
            self::Saturday => 6,
            self::Sunday => 7,
        };
    }
}
