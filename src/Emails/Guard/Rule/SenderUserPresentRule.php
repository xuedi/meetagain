<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Entity\User;

final readonly class SenderUserPresentRule implements EmailGuardRuleInterface
{
    public function getName(): string
    {
        return 'sender_user_present';
    }

    public function getCost(): EmailGuardCost
    {
        return EmailGuardCost::Free;
    }

    public function evaluate(array $context): EmailGuardResult
    {
        if (!array_key_exists('sender', $context) || !$context['sender'] instanceof User) {
            return EmailGuardResult::error(
                $this->getName(),
                "Context is missing the 'sender' key, or it is not a User instance.",
                'sender',
            );
        }

        return EmailGuardResult::pass($this->getName());
    }
}
