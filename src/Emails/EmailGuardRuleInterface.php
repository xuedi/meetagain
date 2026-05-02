<?php declare(strict_types=1);

namespace App\Emails;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface EmailGuardRuleInterface
{
    /**
     * Stable identifier used for translation keys, log lines, and the admin guard-detail page.
     */
    public function getName(): string;

    /**
     * Cost tier for ordering. Email types MUST list rules with non-decreasing cost; this is enforced
     * by a unit test, not by a runtime sort.
     */
    public function getCost(): EmailGuardCost;

    /**
     * Decide whether this rule's predicate holds for the given context. Pass means the chain may
     * continue; Skip means cleanly abort without sending; Error means the caller broke the contract.
     */
    public function evaluate(array $context): EmailGuardResult;
}
