<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Enum\RecurrenceMode;
use App\Enum\RecurrenceOrdinal;
use App\Enum\RecurrencePeriod;
use App\Enum\Weekday;
use App\Exception\Event\InvalidRecurrencePatternException;
use App\ValueObject\RecurrencePattern;
use DateTimeInterface;
use IntlDateFormatter;
use Locale;
use RRule\RRule;

final readonly class RecurrencePreviewService
{
    private const int CANDIDATE_COUNT = 6;
    private const int YEARLY_CANDIDATE_COUNT = 12;
    private const string LABEL_PATTERN = 'EEE, d MMM y';

    public function __construct(
        private RecurrenceDescriber $describer,
    ) {}

    /**
     * @return list<array{date: string, label: string, spec: string, summary: string}>
     */
    public function candidates(
        RecurrenceMode $mode,
        RecurrencePeriod $period,
        ?RecurrenceOrdinal $ordinal,
        ?Weekday $weekday,
        ?int $dayOfMonth,
        DateTimeInterface $after,
        ?string $locale = null,
    ): array {
        $isYearly = RecurrencePeriod::Year === $period;

        // A yearly rule needs a BYMONTH only the picked date supplies, so walk the monthly equivalent.
        $probePeriod = $isYearly ? RecurrencePeriod::Month : $period;
        $probe = $this->buildPattern($mode, $probePeriod, $ordinal, $weekday, $dayOfMonth, null);

        $rrule = new RRule(
            sprintf(
                '%s;COUNT=%d',
                $probe->toRfcString(),
                $isYearly ? self::YEARLY_CANDIDATE_COUNT : self::CANDIDATE_COUNT,
            ),
            $after->format('Y-m-d'),
        );

        $intlLocale = $locale ?? Locale::getDefault();
        $candidates = [];
        foreach ($rrule as $occurrence) {
            $pattern = $this->buildPattern(
                $mode,
                $period,
                $ordinal,
                $weekday,
                $dayOfMonth,
                $isYearly ? (int) $occurrence->format('n') : null,
            );

            $candidates[] = [
                'date' => $occurrence->format('Y-m-d'),
                'label' => $this->formatLabel($occurrence, $intlLocale),
                'spec' => $pattern->toRfcString(),
                'summary' => $this->describer->describe($pattern, $locale),
            ];
        }

        return $candidates;
    }

    private function buildPattern(
        RecurrenceMode $mode,
        RecurrencePeriod $period,
        ?RecurrenceOrdinal $ordinal,
        ?Weekday $weekday,
        ?int $dayOfMonth,
        ?int $anchorMonth,
    ): RecurrencePattern {
        if (RecurrenceMode::DayOfMonth === $mode) {
            if (null === $dayOfMonth) {
                throw new InvalidRecurrencePatternException('A day-of-month pattern requires a day.');
            }

            return RecurrencePattern::dayOfMonth($period, $dayOfMonth, $anchorMonth);
        }

        if (!$weekday instanceof Weekday) {
            throw new InvalidRecurrencePatternException('A weekday pattern requires a weekday.');
        }

        return RecurrencePattern::weekday($period, $weekday, $ordinal, $anchorMonth);
    }

    private function formatLabel(DateTimeInterface $date, string $intlLocale): string
    {
        $formatter = new IntlDateFormatter(
            $intlLocale,
            IntlDateFormatter::MEDIUM,
            IntlDateFormatter::NONE,
            null,
            null,
            self::LABEL_PATTERN,
        );

        return $formatter->format($date) ?: $date->format('Y-m-d');
    }
}
