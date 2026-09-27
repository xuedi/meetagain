<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;

final readonly class WeekStartEndPresentRule implements GuardRuleInterface
{
    public function getName(): string
    {
        return 'week_start_end_present';
    }

    public function getCost(): GuardCost
    {
        return GuardCost::Free;
    }

    public function evaluate(array $context): GuardResult
    {
        foreach (['weekStart', 'weekEnd'] as $key) {
            if (!array_key_exists($key, $context) || $context[$key] === null) {
                return GuardResult::error($this->getName(), "Context is missing 'weekStart' and/or 'weekEnd'.", $key);
            }
        }

        return GuardResult::pass($this->getName());
    }
}
