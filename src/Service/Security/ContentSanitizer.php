<?php declare(strict_types=1);

namespace App\Service\Security;

use Symfony\Component\HtmlSanitizer\HtmlSanitizerInterface;

final readonly class ContentSanitizer
{
    public function __construct(
        private HtmlSanitizerInterface $messageContent,
        private HtmlSanitizerInterface $messagePlain,
    ) {}

    public function toPlainText(string $input): string
    {
        $safe = $this->messagePlain->sanitize($input);

        return trim(html_entity_decode(strip_tags($safe), ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    // htmlspecialchars, not a sanitizer policy: the sanitizer drops disallowed tags together with
    // their content, which would silently eat the angle brackets and code in a bug report.
    public function escape(string $input): string
    {
        return trim(htmlspecialchars($input, ENT_QUOTES | ENT_HTML5, 'UTF-8'));
    }

    public function basic(string $input): string
    {
        return $this->messageContent->sanitize($input);
    }
}
