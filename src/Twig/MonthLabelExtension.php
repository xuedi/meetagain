<?php declare(strict_types=1);

namespace App\Twig;

use DateTimeInterface;
use IntlDateFormatter;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class MonthLabelExtension extends AbstractExtension
{
    #[Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('month_label', $this->monthLabel(...)),
        ];
    }

    public function monthLabel(DateTimeInterface $date, string $locale): string
    {
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $date->getTimezone(), null, 'MMM');
        $label = $formatter->format($date);

        return mb_strtoupper(rtrim((string) $label, '.'), 'UTF-8');
    }
}
