<?php declare(strict_types=1);

namespace App\ValueObject;

use App\Enum\EventInterval;
use App\Enum\RecurrenceMode;
use App\Enum\RecurrenceOrdinal;
use App\Enum\RecurrencePeriod;
use App\Enum\Weekday;
use App\Exception\Event\InvalidRecurrencePatternException;
use DateTimeInterface;

final readonly class RecurrencePattern
{
    public const int LAST_DAY_OF_MONTH = -1;

    private const array MONTH_LENGTHS = [1 => 31, 2 => 29, 3 => 31, 4 => 30, 5 => 31, 6 => 30, 7 => 31, 8 => 31, 9 => 30, 10 => 31, 11 => 30, 12 => 31];

    /**
     * @param list<RecurrenceOrdinal> $ordinals
     * @param list<Weekday>           $weekdays
     * @param list<int>               $daysOfMonth
     */
    private function __construct(
        public RecurrenceMode $mode,
        public RecurrencePeriod $period,
        public array $ordinals,
        public array $weekdays,
        public array $daysOfMonth,
        public ?int $anchorMonth,
    ) {}

    /**
     * @param list<Weekday>|Weekday                        $weekdays
     * @param list<RecurrenceOrdinal>|RecurrenceOrdinal|null $ordinals
     */
    public static function weekday(RecurrencePeriod $period, array|Weekday $weekdays, array|RecurrenceOrdinal|null $ordinals = null, ?int $anchorMonth = null): self
    {
        if (!$period->carriesDayRule()) {
            throw new InvalidRecurrencePatternException(sprintf('Period "%s" cannot carry a weekday rule.', $period->value));
        }

        $weekdays = Weekday::sort(is_array($weekdays) ? $weekdays : [$weekdays]);
        if ([] === $weekdays) {
            throw new InvalidRecurrencePatternException('A weekday pattern requires at least one weekday.');
        }

        $ordinals = RecurrenceOrdinal::sort(match (true) {
            null === $ordinals => [],
            is_array($ordinals) => $ordinals,
            default => [$ordinals],
        });

        if ($period->isWeekly()) {
            $ordinals = [];
        } elseif ([] === $ordinals) {
            throw new InvalidRecurrencePatternException(sprintf('Period "%s" requires an ordinal.', $period->value));
        } elseif (1 !== count($weekdays)) {
            throw new InvalidRecurrencePatternException(sprintf('Period "%s" combines several ordinals with exactly one weekday.', $period->value));
        }

        return new self(RecurrenceMode::Weekday, $period, $ordinals, $weekdays, [], self::resolveAnchorMonth($period, $anchorMonth));
    }

    /**
     * @param list<int>|int $daysOfMonth
     */
    public static function dayOfMonth(RecurrencePeriod $period, array|int $daysOfMonth, ?int $anchorMonth = null): self
    {
        if ($period->isWeekly() || !$period->carriesDayRule()) {
            throw new InvalidRecurrencePatternException(sprintf('Period "%s" cannot carry a day of month.', $period->value));
        }

        $days = self::sortDays(is_array($daysOfMonth) ? $daysOfMonth : [$daysOfMonth]);
        if ([] === $days) {
            throw new InvalidRecurrencePatternException('A day-of-month pattern requires at least one day.');
        }

        $anchorMonth = self::resolveAnchorMonth($period, $anchorMonth);
        foreach ($days as $day) {
            // "Last day" is a different sentence from a numbered day, so it never joins a list.
            if (self::LAST_DAY_OF_MONTH === $day) {
                if (1 !== count($days)) {
                    throw new InvalidRecurrencePatternException('The last day of the month cannot be combined with other days.');
                }

                continue;
            }
            if ($day < 1 || $day > 31) {
                throw new InvalidRecurrencePatternException(sprintf('Day of month "%d" is out of range.', $day));
            }
            if (null !== $anchorMonth && $day > self::MONTH_LENGTHS[$anchorMonth]) {
                throw new InvalidRecurrencePatternException(sprintf('Month %d never has a day %d.', $anchorMonth, $day));
            }
        }

        return new self(RecurrenceMode::DayOfMonth, $period, [], [], $days, $anchorMonth);
    }

    public static function daily(): self
    {
        return new self(RecurrenceMode::EveryDay, RecurrencePeriod::Day, [], [], [], null);
    }

    public static function fromRfcString(string $spec): self
    {
        $parts = self::splitSpec($spec);
        $period = self::resolvePeriod($parts);
        $anchorMonth = isset($parts['BYMONTH']) ? (int) $parts['BYMONTH'] : null;

        if (!$period->carriesDayRule()) {
            if (isset($parts['BYDAY']) || isset($parts['BYMONTHDAY'])) {
                throw new InvalidRecurrencePatternException('A daily rule cannot carry BYDAY or BYMONTHDAY.');
            }

            return self::daily();
        }

        if (isset($parts['BYMONTHDAY'])) {
            return self::dayOfMonth(
                $period,
                array_map(static fn(string $day): int => (int) $day, explode(',', $parts['BYMONTHDAY'])),
                $anchorMonth,
            );
        }

        if (isset($parts['BYDAY'])) {
            return self::fromByDay($period, $parts['BYDAY'], $anchorMonth);
        }

        throw new InvalidRecurrencePatternException('Rule must carry either BYDAY or BYMONTHDAY.');
    }

    public static function fromInterval(EventInterval $interval, DateTimeInterface $anchor): ?self
    {
        $day = (int) $anchor->format('j');
        $month = (int) $anchor->format('n');

        return match ($interval) {
            EventInterval::Daily => self::daily(),
            EventInterval::Weekly => self::weekday(RecurrencePeriod::Week, Weekday::fromDate($anchor)),
            EventInterval::BiMonthly => self::weekday(RecurrencePeriod::TwoWeeks, Weekday::fromDate($anchor)),
            EventInterval::Monthly => self::dayOfMonth(RecurrencePeriod::Month, $day),
            EventInterval::Yearly => self::dayOfMonth(RecurrencePeriod::Year, $day, $month),
            EventInterval::Custom => null,
        };
    }

    public function toRfcString(): string
    {
        $parts = ['FREQ=' . $this->period->frequency()];

        if ($this->period->interval() > 1) {
            $parts[] = 'INTERVAL=' . $this->period->interval();
        }
        if (null !== $this->anchorMonth) {
            $parts[] = 'BYMONTH=' . $this->anchorMonth;
        }

        $byPart = match ($this->mode) {
            RecurrenceMode::EveryDay => null,
            RecurrenceMode::Weekday => 'BYDAY=' . implode(',', $this->byDayEntries()),
            RecurrenceMode::DayOfMonth => 'BYMONTHDAY=' . implode(',', $this->daysOfMonth),
        };
        if (null !== $byPart) {
            $parts[] = $byPart;
        }

        return implode(';', $parts);
    }

    public function isLastDayOfMonth(): bool
    {
        return [self::LAST_DAY_OF_MONTH] === $this->daysOfMonth;
    }

    /**
     * @param list<int> $days
     *
     * @return list<int>
     */
    private static function sortDays(array $days): array
    {
        $unique = array_values(array_unique($days, SORT_REGULAR));
        sort($unique);

        return $unique;
    }

    /**
     * @return list<string>
     */
    private function byDayEntries(): array
    {
        if ([] === $this->ordinals) {
            return array_map(static fn(Weekday $weekday): string => $weekday->value, $this->weekdays);
        }

        $weekday = $this->weekdays[0];

        return array_map(static fn(RecurrenceOrdinal $ordinal): string => $ordinal->value . $weekday->value, $this->ordinals);
    }

    /**
     * @return array<string, string>
     */
    private static function splitSpec(string $spec): array
    {
        $parts = [];
        foreach (explode(';', $spec) as $chunk) {
            if ('' === $chunk) {
                continue;
            }

            $pair = explode('=', $chunk, 2);
            if (2 !== count($pair)) {
                throw new InvalidRecurrencePatternException(sprintf('Malformed rule segment "%s".', $chunk));
            }

            $parts[strtoupper($pair[0])] = strtoupper($pair[1]);
        }

        return $parts;
    }

    /**
     * @param array<string, string> $parts
     */
    private static function resolvePeriod(array $parts): RecurrencePeriod
    {
        $frequency = $parts['FREQ'] ?? '';
        $interval = isset($parts['INTERVAL']) ? (int) $parts['INTERVAL'] : 1;

        foreach (RecurrencePeriod::cases() as $case) {
            if ($case->frequency() === $frequency && $case->interval() === $interval) {
                return $case;
            }
        }

        throw new InvalidRecurrencePatternException(sprintf('Unsupported frequency "%s" with interval %d.', $frequency, $interval));
    }

    private static function fromByDay(RecurrencePeriod $period, string $byDay, ?int $anchorMonth): self
    {
        $ordinals = [];
        $weekdays = [];
        foreach (explode(',', $byDay) as $entry) {
            $matches = [];
            if (1 !== preg_match('/^(-?\d+)?(MO|TU|WE|TH|FR|SA|SU)$/', $entry, $matches)) {
                throw new InvalidRecurrencePatternException(sprintf('Unsupported BYDAY value "%s".', $entry));
            }

            $weekdays[] = Weekday::from($matches[2]);
            if ('' === ($matches[1] ?? '')) {
                continue;
            }

            $ordinal = RecurrenceOrdinal::tryFrom((int) $matches[1]);
            if (!$ordinal instanceof RecurrenceOrdinal) {
                throw new InvalidRecurrencePatternException(sprintf('Unsupported BYDAY ordinal in "%s".', $entry));
            }
            $ordinals[] = $ordinal;
        }

        if ([] !== $ordinals && count($ordinals) !== count($weekdays)) {
            throw new InvalidRecurrencePatternException(sprintf('BYDAY "%s" mixes entries with and without an ordinal.', $byDay));
        }
        if (!$period->isWeekly() && [] === $ordinals) {
            throw new InvalidRecurrencePatternException(sprintf('Unsupported BYDAY ordinal in "%s".', $byDay));
        }

        return self::weekday($period, $weekdays, $ordinals, $anchorMonth);
    }

    private static function resolveAnchorMonth(RecurrencePeriod $period, ?int $anchorMonth): ?int
    {
        if (RecurrencePeriod::Year !== $period) {
            return null;
        }

        $isValidMonth = null !== $anchorMonth && $anchorMonth >= 1 && $anchorMonth <= 12;
        if (!$isValidMonth) {
            throw new InvalidRecurrencePatternException('A yearly pattern requires an anchor month between 1 and 12.');
        }

        return $anchorMonth;
    }
}
