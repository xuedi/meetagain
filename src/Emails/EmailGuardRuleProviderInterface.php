<?php declare(strict_types=1);

namespace App\Emails;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Plugin extension point: lets a plugin splice extra guard rules into a core email type's chain
 * without modifying core. Rules returned from a provider are appended after the email type's own
 * `getGuardRules()` and run after them.
 */
#[AutoconfigureTag]
interface EmailGuardRuleProviderInterface
{
    /**
     * @return list<EmailGuardRuleInterface>
     */
    public function getRulesFor(string $emailIdentifier): array;
}
