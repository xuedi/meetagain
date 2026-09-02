<?php declare(strict_types=1);

namespace App\Twig;

use App\Service\Security\ContentSanitizer;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class ContentRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private ContentSanitizer $sanitizer,
    ) {}

    // Re-applies the allow-list on output, so rows stored before sanitization existed cannot inject.
    public function safeMessage(?string $content): string
    {
        return nl2br($this->sanitizer->basic((string) $content));
    }
}
