<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class FormatDurationExtension extends AbstractExtension
{
    #[Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('format_duration', $this->formatDuration(...)),
        ];
    }

    public function formatDuration(int|float $seconds): string
    {
        $seconds = (int) max(0, $seconds);

        if ($seconds < 60) {
            return $seconds . 's';
        }

        if ($seconds < 3600) {
            $m = intdiv($seconds, 60);
            $s = $seconds % 60;

            return $s > 0 ? $m . 'm ' . $s . 's' : $m . 'm';
        }

        $h = intdiv($seconds, 3600);
        $m = intdiv($seconds % 3600, 60);

        return $m > 0 ? $h . 'h ' . $m . 'm' : $h . 'h';
    }
}
