<?php declare(strict_types=1);

namespace Module\Email\Contract;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Contributes extra guard rules to an email type's chain, appended after its own `getGuardRules()`.
 */
#[AutoconfigureTag]
interface GuardRuleProviderInterface
{
    /**
     * @return list<GuardRuleInterface>
     */
    public function getRulesFor(string $emailIdentifier): array;
}
