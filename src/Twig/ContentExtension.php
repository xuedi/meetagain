<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\Security\ContentSanitizer;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

final class ContentExtension extends AbstractExtension
{
    public function __construct(
        private readonly ContentSanitizer $sanitizer,
    ) {}

    public function getFilters(): array
    {
        return [
            new TwigFilter('safe_message', $this->safeMessage(...), ['is_safe' => ['html']]),
        ];
    }

    // Re-applies the allow-list on output, so rows stored before sanitization existed cannot inject.
    public function safeMessage(?string $content): string
    {
        return nl2br($this->sanitizer->basic((string) $content));
    }
}
