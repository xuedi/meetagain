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

    private function __construct(
        public RecurrenceMode $mode,
        public RecurrencePeriod $period,
        public ?RecurrenceOrdinal $ordinal,
        public ?Weekday $weekday,
        public ?int $dayOfMonth,
        public ?int $anchorMonth,
    ) {}

    public static function weekday(RecurrencePeriod $period, Weekday $weekday, ?RecurrenceOrdinal $ordinal = null, ?int $anchorMonth = null): self
    {
        if ($period->isWeekly()) {
            $ordinal = null;
        } elseif (!$ordinal instanceof RecurrenceOrdinal) {
            throw new InvalidRecurrencePatternException(sprintf('Period "%s" requires an ordinal.', $period->value));
        }

        return new self(RecurrenceMode::Weekday, $period, $ordinal, $weekday, null, self::resolveAnchorMonth($period, $anchorMonth));
    }

    public static function dayOfMonth(RecurrencePeriod $period, int $dayOfMonth, ?int $anchorMonth = null): self
    {
        if ($period->isWeekly()) {
            throw new InvalidRecurrencePatternException(sprintf('Period "%s" cannot carry a day of month.', $period->value));
        }

        $isLastDay = self::LAST_DAY_OF_MONTH === $dayOfMonth;
        $isCalendarDay = $dayOfMonth >= 1 && $dayOfMonth <= 31;
        if (!$isLastDay && !$isCalendarDay) {
            throw new InvalidRecurrencePatternException(sprintf('Day of month "%d" is out of range.', $dayOfMonth));
        }

        return new self(RecurrenceMode::DayOfMonth, $period, null, null, $dayOfMonth, self::resolveAnchorMonth($period, $anchorMonth));
    }

    public static function fromRfcString(string $spec): self
    {
        $parts = self::splitSpec($spec);
        $period = self::resolvePeriod($parts);
        $anchorMonth = isset($parts['BYMONTH']) ? (int) $parts['BYMONTH'] : null;

        if (isset($parts['BYMONTHDAY'])) {
            return self::dayOfMonth($period, (int) $parts['BYMONTHDAY'], $anchorMonth);
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
            EventInterval::Weekly => self::weekday(RecurrencePeriod::Week, Weekday::fromDate($anchor)),
            EventInterval::BiMonthly => self::weekday(RecurrencePeriod::TwoWeeks, Weekday::fromDate($anchor)),
            EventInterval::Monthly => self::dayOfMonth(RecurrencePeriod::Month, $day),
            EventInterval::Yearly => self::dayOfMonth(RecurrencePeriod::Year, $day, $month),
            EventInterval::Daily, EventInterval::Custom => null,
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

        $parts[] = match ($this->mode) {
            RecurrenceMode::Weekday => sprintf('BYDAY=%s%s', $this->ordinal->value ?? '', $this->weekday->value ?? ''),
            RecurrenceMode::DayOfMonth => 'BYMONTHDAY=' . $this->dayOfMonth,
        };

        return implode(';', $parts);
    }

    public function isLastDayOfMonth(): bool
    {
        return self::LAST_DAY_OF_MONTH === $this->dayOfMonth;
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
        $matches = [];
        if (1 !== preg_match('/^(-?\d+)?(MO|TU|WE|TH|FR|SA|SU)$/', $byDay, $matches)) {
            throw new InvalidRecurrencePatternException(sprintf('Unsupported BYDAY value "%s".', $byDay));
        }

        $ordinal = '' === $matches[1] ? null : RecurrenceOrdinal::tryFrom((int) $matches[1]);
        if (!$period->isWeekly() && !$ordinal instanceof RecurrenceOrdinal) {
            throw new InvalidRecurrencePatternException(sprintf('Unsupported BYDAY ordinal in "%s".', $byDay));
        }

        return self::weekday($period, Weekday::from($matches[2]), $ordinal, $anchorMonth);
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
