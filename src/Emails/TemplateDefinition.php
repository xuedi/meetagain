<?php declare(strict_types=1);

namespace App\Emails;

final readonly class TemplateDefinition
{
    /** @param list<string> $variables */
    public function __construct(
        public string $identifier,
        public string $subject,
        public string $body,
        public array $variables,
    ) {}
}
