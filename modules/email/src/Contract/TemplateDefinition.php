<?php declare(strict_types=1);

namespace Module\Email\Contract;

final readonly class TemplateDefinition
{
    /**
     * @param list<string> $variables
     * @param list<string> $htmlVariables the variables substituted without escaping
     */
    public function __construct(
        public string $identifier,
        public string $subject,
        public string $body,
        public array $variables,
        public array $htmlVariables = [],
    ) {}
}
