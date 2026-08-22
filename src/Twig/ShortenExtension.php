<?php declare(strict_types=1);

namespace App\Twig;

use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class ShortenExtension extends AbstractExtension
{
    public const int DEFAULT_WIDTH = 30;

    private const int HARD_CAP_FACTOR = 10;

    #[Override]
    public function getFilters(): array
    {
        return [
            new TwigFilter('shortened', $this->shortened(...), ['is_safe' => ['html']]),
        ];
    }

    public function shortened(?string $value, int $width = self::DEFAULT_WIDTH): string
    {
        $text = (string) $value;
        if ($text === '') {
            return '';
        }

        $width = max(1, $width);
        $cap = $width * self::HARD_CAP_FACTOR;
        $tooltip = mb_strlen($text) > $cap ? mb_substr($text, 0, $cap) . '...' : $text;
        $escaped = htmlspecialchars($tooltip, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');

        if (mb_strlen($text) <= $width) {
            return $escaped;
        }

        return sprintf(
            '<span class="is-truncated" style="--truncate-ch: %d" title="%s">%s</span>',
            $width,
            $escaped,
            $escaped,
        );
    }
}
