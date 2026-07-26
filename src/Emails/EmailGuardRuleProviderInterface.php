<?php declare(strict_types=1);

namespace App\Emails;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes extra guard rules to an email type's chain, appended after its own `getGuardRules()`.
 */
#[AutoconfigureTag]
interface EmailGuardRuleProviderInterface
{
    /**
     * @return list<EmailGuardRuleInterface>
     */
    public function getRulesFor(string $emailIdentifier): array;
}
