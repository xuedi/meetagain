<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Entity\User;

final readonly class RecipientUserPresentRule implements EmailGuardRuleInterface
{
    public function getName(): string
    {
        return 'recipient_user_present';
    }

    public function getCost(): EmailGuardCost
    {
        return EmailGuardCost::Free;
    }

    public function evaluate(array $context): EmailGuardResult
    {
        if (!array_key_exists('user', $context) || !$context['user'] instanceof User) {
            return EmailGuardResult::error(
                $this->getName(),
                "Context is missing the 'user' key, or it is not a User instance.",
                'user',
            );
        }

        return EmailGuardResult::pass($this->getName());
    }
}
