<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Enum\RecurrenceMode;
use App\Enum\RecurrencePeriod;
use App\Enum\Weekday;
use App\ValueObject\RecurrencePattern;
use DateTimeImmutable;
use DateTimeInterface;
use IntlDateFormatter;
use Locale;
use NumberFormatter;
use Symfony\Contracts\Translation\TranslatorInterface;

final readonly class RecurrenceDescriber
{
    public function __construct(
        private TranslatorInterface $translator,
    ) {}

    public function describe(RecurrencePattern $pattern, ?string $locale = null): string
    {
        $intlLocale = $locale ?? Locale::getDefault();

        return $this->translator->trans(
            $this->sentenceKey($pattern),
            $this->parameters($pattern, $intlLocale),
            null,
            $locale,
        );
    }

    public function weekdayName(Weekday $weekday, ?string $locale = null): string
    {
        // 2024-01-01 was a Monday, so ISO day N lands on 2024-01-0N.
        return $this->formatDate(
            new DateTimeImmutable(sprintf('2024-01-0%d', $weekday->isoNumber())),
            $locale ?? Locale::getDefault(),
            'EEEE',
        );
    }

    private function sentenceKey(RecurrencePattern $pattern): string
    {
        $slot = match ($pattern->period) {
            RecurrencePeriod::Week => 'week',
            RecurrencePeriod::TwoWeeks => 'two_weeks',
            RecurrencePeriod::Month => 'month',
            RecurrencePeriod::TwoMonths => 'two_months',
            RecurrencePeriod::Quarter => 'quarter',
            RecurrencePeriod::Year => 'year',
        };

        if (RecurrenceMode::Weekday === $pattern->mode) {
            return 'admin_event.recurrence_text_weekday_' . $slot;
        }

        return $pattern->isLastDayOfMonth()
            ? 'admin_event.recurrence_text_last_day_' . $slot
            : 'admin_event.recurrence_text_day_' . $slot;
    }

    /**
     * Both day forms are passed because catalogues need different ones: en "15th", zh bare "15".
     *
     * @return array<string, string>
     */
    private function parameters(RecurrencePattern $pattern, string $intlLocale): array
    {
        $parameters = [];

        if (null !== $pattern->weekday) {
            $parameters['%weekday%'] = $this->weekdayName($pattern->weekday, $intlLocale);
        }

        if (null !== $pattern->ordinal) {
            $parameters['%ordinal%'] = $this->translator->trans($pattern->ordinal->label(), [], null, $intlLocale);
        }

        if (null !== $pattern->dayOfMonth && !$pattern->isLastDayOfMonth()) {
            $parameters['%day%'] = (string) $pattern->dayOfMonth;
            $parameters['%day_ordinal%'] = (new NumberFormatter($intlLocale, NumberFormatter::ORDINAL))
                ->format($pattern->dayOfMonth) ?: (string) $pattern->dayOfMonth;
        }

        if (null !== $pattern->anchorMonth) {
            $parameters['%month%'] = $this->formatDate(
                new DateTimeImmutable(sprintf('2000-%02d-01', $pattern->anchorMonth)),
                $intlLocale,
                'LLLL',
            );
        }

        return $parameters;
    }

    private function formatDate(DateTimeInterface $date, string $intlLocale, string $pattern): string
    {
        $formatter = new IntlDateFormatter(
            $intlLocale,
            IntlDateFormatter::FULL,
            IntlDateFormatter::NONE,
            null,
            null,
            $pattern,
        );

        return $formatter->format($date) ?: '';
    }
}
