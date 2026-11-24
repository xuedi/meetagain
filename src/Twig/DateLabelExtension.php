<?php declare(strict_types=1);

namespace App\Twig;

use DateTimeInterface;
use IntlDateFormatter;
use IntlDatePatternGenerator;
use Override;
use Twig\Environment;
use Twig\Extension\AbstractExtension;
use Twig\Extension\CoreExtension;
use Twig\TwigFilter;

final class DateLabelExtension extends AbstractExtension
{
    #[Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('month_label', $this->monthLabel(...), ['needs_environment' => true]),
            new TwigFilter('date_label', $this->dateLabel(...), ['needs_environment' => true]),
        ];
    }

    public function monthLabel(Environment $twig, DateTimeInterface $date, string $locale): string
    {
        $label = $this->format($twig, $date, $locale, 'MMM');

        return mb_strtoupper(rtrim($label, '.'), 'UTF-8');
    }

    public function dateLabel(Environment $twig, DateTimeInterface $date, string $locale, string $skeleton): string
    {
        $pattern = new IntlDatePatternGenerator($locale)->getBestPattern($skeleton);

        return $this->format($twig, $date, $locale, (string) $pattern);
    }

    private function format(Environment $twig, DateTimeInterface $date, string $locale, string $pattern): string
    {
        $timezone = $twig->getExtension(CoreExtension::class)->getTimezone();
        $formatter = new IntlDateFormatter($locale, IntlDateFormatter::NONE, IntlDateFormatter::NONE, $timezone, null, $pattern);

        return (string) $formatter->format($date);
    }
}
