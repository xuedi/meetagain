<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Enum\RecurrenceMode;
use App\Enum\RecurrenceOrdinal;
use App\Enum\Weekday;
use App\ValueObject\RecurrencePattern;
use DateTimeImmutable;
use DateTimeInterface;

final readonly class OccurrenceCalculator
{
    // Bounds the walk for sparse rules (a yearly leap-day rule skips three periods out of four).
    private const int MAX_PERIOD_STEPS = 1200;

    /**
     * @return list<DateTimeImmutable> midnight dates after the anchor, ascending, excluding the anchor's own date
     */
    public function until(RecurrencePattern $pattern, DateTimeInterface $anchor, DateTimeInterface $until): array
    {
        return $this->walk($pattern, $anchor, DateTimeImmutable::createFromInterface($until)->setTime(0, 0), null, false);
    }

    /**
     * @return list<DateTimeImmutable> midnight dates after the anchor, ascending, excluding the anchor's own date
     */
    public function take(RecurrencePattern $pattern, DateTimeInterface $anchor, int $count): array
    {
        return $count < 1 ? [] : $this->walk($pattern, $anchor, null, $count, false);
    }

    /**
     * @return list<DateTimeImmutable> midnight dates on or after the given day, ascending
     */
    public function takeFrom(RecurrencePattern $pattern, DateTimeInterface $from, int $count): array
    {
        return $count < 1 ? [] : $this->walk($pattern, $from, null, $count, true);
    }

    /**
     * @return list<DateTimeImmutable>
     */
    private function walk(RecurrencePattern $pattern, DateTimeInterface $anchor, ?DateTimeImmutable $until, ?int $count, bool $includeAnchorDate): array
    {
        $anchorDate = DateTimeImmutable::createFromInterface($anchor)->setTime(0, 0);
        $cursor = $this->firstPeriodStart($pattern, $anchorDate);

        $occurrences = [];
        for ($step = 0; $step < self::MAX_PERIOD_STEPS; ++$step) {
            if ($until instanceof DateTimeImmutable && $cursor > $until) {
                break;
            }

            foreach ($this->datesInPeriod($pattern, $cursor) as $date) {
                if ($includeAnchorDate ? $date < $anchorDate : $date <= $anchorDate) {
                    continue;
                }
                if ($until instanceof DateTimeImmutable && $date > $until) {
                    return $occurrences;
                }

                $occurrences[] = $date;
                if (null !== $count && count($occurrences) >= $count) {
                    return $occurrences;
                }
            }

            $cursor = $this->advance($pattern, $cursor);
        }

        return $occurrences;
    }

    private function firstPeriodStart(RecurrencePattern $pattern, DateTimeImmutable $anchorDate): DateTimeImmutable
    {
        return match ($pattern->period->frequency()) {
            // WKST is Monday, so a Sunday anchor belongs to the week that started six days earlier.
            'WEEKLY' => $anchorDate->modify(sprintf('-%d days', (int) $anchorDate->format('N') - 1)),
            'MONTHLY' => $this->dayOf($anchorDate, 1),
            'YEARLY' => $anchorDate->setDate((int) $anchorDate->format('Y'), (int) $pattern->anchorMonth, 1),
            default => $anchorDate,
        };
    }

    private function advance(RecurrencePattern $pattern, DateTimeImmutable $cursor): DateTimeImmutable
    {
        return match ($pattern->period->frequency()) {
            'WEEKLY' => $cursor->modify(sprintf('+%d days', 7 * $pattern->period->interval())),
            'MONTHLY' => $cursor->modify(sprintf('+%d months', $pattern->period->interval())),
            'YEARLY' => $cursor->modify('+1 year'),
            default => $cursor->modify('+1 day'),
        };
    }

    /**
     * @return list<DateTimeImmutable>
     */
    private function datesInPeriod(RecurrencePattern $pattern, DateTimeImmutable $periodStart): array
    {
        return match ($pattern->mode) {
            RecurrenceMode::EveryDay => [$periodStart],
            RecurrenceMode::Weekday => $pattern->period->isWeekly()
                ? $this->weekdaysInWeek($pattern, $periodStart)
                : $this->weekdaysInMonth($pattern, $periodStart),
            RecurrenceMode::DayOfMonth => $this->dayInMonth($pattern, $periodStart),
        };
    }

    /**
     * @return list<DateTimeImmutable>
     */
    private function weekdaysInWeek(RecurrencePattern $pattern, DateTimeImmutable $weekStart): array
    {
        return array_map(static fn(Weekday $weekday): DateTimeImmutable => $weekStart->modify(sprintf(
            '+%d days',
            $weekday->isoNumber() - 1,
        )), $pattern->weekdays);
    }

    /**
     * @return list<DateTimeImmutable>
     */
    private function weekdaysInMonth(RecurrencePattern $pattern, DateTimeImmutable $monthStart): array
    {
        $weekday = $pattern->weekdays[0];

        $dates = [];
        foreach ($pattern->ordinals as $ordinal) {
            $date = $this->nthWeekday($monthStart, $weekday, $ordinal);
            if ($date instanceof DateTimeImmutable) {
                $dates[$date->format('Y-m-d')] = $date;
            }
        }
        ksort($dates);

        return array_values($dates);
    }

    /**
     * @return list<DateTimeImmutable>
     */
    private function dayInMonth(RecurrencePattern $pattern, DateTimeImmutable $monthStart): array
    {
        $daysInMonth = (int) $monthStart->format('t');
        if ($pattern->isLastDayOfMonth()) {
            return [$this->dayOf($monthStart, $daysInMonth)];
        }

        $dates = [];
        foreach ($pattern->daysOfMonth as $day) {
            if ($day > $daysInMonth) {
                continue;
            }

            $dates[] = $this->dayOf($monthStart, $day);
        }

        return $dates;
    }

    private function nthWeekday(DateTimeImmutable $monthStart, Weekday $weekday, RecurrenceOrdinal $ordinal): ?DateTimeImmutable
    {
        $daysInMonth = (int) $monthStart->format('t');

        if (RecurrenceOrdinal::Last === $ordinal) {
            $lastIso = (int) $this->dayOf($monthStart, $daysInMonth)->format('N');

            return $this->dayOf($monthStart, $daysInMonth - (($lastIso - $weekday->isoNumber() + 7) % 7));
        }

        $offset = ($weekday->isoNumber() - (int) $monthStart->format('N') + 7) % 7;
        $day = 1 + $offset + (($ordinal->value - 1) * 7);

        return $day <= $daysInMonth ? $this->dayOf($monthStart, $day) : null;
    }

    private function dayOf(DateTimeImmutable $date, int $day): DateTimeImmutable
    {
        return $date->setDate((int) $date->format('Y'), (int) $date->format('n'), $day);
    }
}
