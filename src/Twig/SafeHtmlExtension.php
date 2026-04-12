<?php declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class SafeHtmlExtension extends AbstractExtension
{
    private const array ALLOWED_TAGS = ['b', 'strong', 'em', 'i', 'u', 'p', 'br'];

    public function getFilters(): array
    {
        return [
            new TwigFilter('safe_html', $this->safeHtml(...), ['is_safe' => ['html']]),
        ];
    }

    public function safeHtml(string $text): string
    {
        $text = strip_tags($text, self::ALLOWED_TAGS);

        $withAttributes = implode('|', array_diff(self::ALLOWED_TAGS, ['br']));
        $text = (string) preg_replace('/<(' . $withAttributes . ')(?:\s[^>]*)?>/', '<$1>', $text);

        return nl2br($text);
    }
}
