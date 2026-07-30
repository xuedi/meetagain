<?php declare(strict_types=1);

namespace App\Emails;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes editable email templates that are not part of the shipped set. Implementations are
 * asked once per enabled language and their definitions are seeded and reset exactly like the
 * built-in ones.
 */
#[AutoconfigureTag]
interface TemplateProviderInterface
{
    /** @return list<TemplateDefinition> */
    public function getDefinitions(string $language): array;
}
