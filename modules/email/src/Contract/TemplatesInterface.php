<?php declare(strict_types=1);

namespace Module\Email\Contract;

interface TemplatesInterface
{
    /**
     * Renders the stored subject and body in the locale, or in English when the locale has no translation.
     * Throws a RuntimeException when the template is not stored.
     *
     * @param array<string, mixed> $context
     * @return array{subject: string, body: string}
     */
    public function render(string $identifier, string $locale, array $context): array;

    /**
     * Gives every stored template that has no translation in the language the shipped default subject and body.
     */
    public function seedLanguage(string $code): void;

    /**
     * Replaces every stored translation of the template with the shipped default of its language, discarding admin edits.
     */
    public function resetToDefault(string $identifier): void;

    /**
     * Deletes the stored template with every translation. Does nothing when it is not stored.
     */
    public function remove(string $identifier): void;
}
