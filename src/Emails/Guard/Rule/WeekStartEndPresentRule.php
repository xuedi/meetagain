<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;

final readonly class WeekStartEndPresentRule implements EmailGuardRuleInterface
{
    public function getName(): string
    {
        return 'week_start_end_present';
    }

    public function getCost(): EmailGuardCost
    {
        return EmailGuardCost::Free;
    }

    public function evaluate(array $context): EmailGuardResult
    {
        foreach (['weekStart', 'weekEnd'] as $key) {
            if (!array_key_exists($key, $context) || $context[$key] === null) {
                return EmailGuardResult::error(
                    $this->getName(),
                    "Context is missing 'weekStart' and/or 'weekEnd'.",
                    $key,
                );
            }
        }

        return EmailGuardResult::pass($this->getName());
    }
}
