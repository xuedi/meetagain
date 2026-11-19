<?php declare(strict_types=1);

namespace App\Twig;

use DateTimeInterface;
use IntlDateFormatter;
use Override;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\CoreExtension;
use Twig\TwigFilter;

final class MonthLabelExtension extends AbstractExtension
{
    #[Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('month_label', $this->monthLabel(...), ['needs_environment' => true]),
        ];
    }

    public function monthLabel(Environment $twig, DateTimeInterface $date, string $locale): string
    {
        $timezone = $twig->getExtension(CoreExtension::class)->getTimezone();
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $timezone, null, 'MMM');
        $label = $formatter->format($date);

        return mb_strtoupper(rtrim((string) $label, '.'), 'UTF-8');
    }
}
