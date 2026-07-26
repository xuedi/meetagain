<?php declare(strict_types=1);

namespace App\Service\Event;

use App\Enum\RecurrenceMode;
use App\Enum\RecurrenceOrdinal;
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

    // The period slot in every sentence key is the enum's backed value; renaming a case breaks the lookup.
    private function sentenceKey(RecurrencePattern $pattern): string
    {
        if (RecurrenceMode::EveryDay === $pattern->mode) {
            return 'admin_event.recurrence_text_every_day';
        }

        if (RecurrenceMode::Weekday === $pattern->mode) {
            return 'admin_event.recurrence_text_weekday_' . $pattern->period->value;
        }

        return $pattern->isLastDayOfMonth()
            ? 'admin_event.recurrence_text_last_day_' . $pattern->period->value
            : 'admin_event.recurrence_text_day_' . $pattern->period->value;
    }

    /**
     * Both day forms are passed because catalogues need different ones: en "15th", zh bare "15".
     *
     * @return array<string, string>
     */
    private function parameters(RecurrencePattern $pattern, string $intlLocale): array
    {
        $parameters = [];

        if ([] !== $pattern->weekdays) {
            $parameters['%weekday%'] = $this->joinList(
                array_map(fn(Weekday $weekday): string => $this->weekdayName($weekday, $intlLocale), $pattern->weekdays),
                $intlLocale,
            );
        }

        if ([] !== $pattern->ordinals) {
            $parameters['%ordinal%'] = $this->joinList(
                array_map(
                    fn(RecurrenceOrdinal $ordinal): string => $this->translator->trans($ordinal->label(), [], null, $intlLocale),
                    $pattern->ordinals,
                ),
                $intlLocale,
            );
        }

        if ([] !== $pattern->daysOfMonth && !$pattern->isLastDayOfMonth()) {
            $ordinalFormatter = new NumberFormatter($intlLocale, NumberFormatter::ORDINAL);
            $parameters['%day%'] = $this->joinList(
                array_map(static fn(int $day): string => (string) $day, $pattern->daysOfMonth),
                $intlLocale,
            );
            $parameters['%day_ordinal%'] = $this->joinList(
                array_map(
                    static fn(int $day): string => $ordinalFormatter->format($day) ?: (string) $day,
                    $pattern->daysOfMonth,
                ),
                $intlLocale,
            );
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

    /**
     * @param list<string> $items
     */
    private function joinList(array $items, string $intlLocale): string
    {
        if (count($items) < 2) {
            return implode('', $items);
        }

        $last = array_pop($items);

        return implode($this->translator->trans('admin_event.recurrence_list_separator', [], null, $intlLocale), $items)
            . $this->translator->trans('admin_event.recurrence_list_last_separator', [], null, $intlLocale)
            . $last;
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
