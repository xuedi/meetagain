<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Enum\RecurrencePeriod;
use App\ValueObject\RecurrenceBuilderState;
use DateTimeInterface;
use IntlDateFormatter;
use Locale;

final readonly class RecurrencePreviewService
{
    private const int CANDIDATE_COUNT = 6;
    private const int YEARLY_CANDIDATE_COUNT = 12;
    private const string LABEL_PATTERN = 'EEE, d MMM y';

    public function __construct(
        private RecurrenceDescriber $describer,
        private OccurrenceCalculator $calculator,
    ) {}

    /**
     * @return list<array{date: string, label: string, spec: string, summary: string}>
     */
    public function candidates(RecurrenceBuilderState $state, DateTimeInterface $after, ?string $locale = null): array
    {
        $isYearly = RecurrencePeriod::Year === $state->period;

        // A yearly rule needs a BYMONTH only the picked date supplies, so walk the monthly equivalent.
        $probe = $state->pattern(period: $isYearly ? RecurrencePeriod::Month : null);

        // takeFrom, not take: the picked day is a start date, so a matching "after" is a valid candidate.
        $occurrences = $this->calculator->takeFrom(
            $probe,
            $after,
            $isYearly ? self::YEARLY_CANDIDATE_COUNT : self::CANDIDATE_COUNT,
        );

        $intlLocale = $locale ?? Locale::getDefault();
        $candidates = [];
        foreach ($occurrences as $occurrence) {
            $pattern = $state->pattern($isYearly ? (int) $occurrence->format('n') : null);

            $candidates[] = [
                'date' => $occurrence->format('Y-m-d'),
                'label' => $this->formatLabel($occurrence, $intlLocale),
                'spec' => $pattern->toRfcString(),
                'summary' => $this->describer->describe($pattern, $locale),
            ];
        }

        return $candidates;
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
