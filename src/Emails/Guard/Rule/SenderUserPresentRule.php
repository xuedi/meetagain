<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Entity\User;
use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;

final readonly class SenderUserPresentRule implements GuardRuleInterface
{
    public function getName(): string
    {
        return 'sender_user_present';
    }

    public function getCost(): GuardCost
    {
        return GuardCost::Free;
    }

    public function evaluate(array $context): GuardResult
    {
        if (!array_key_exists('sender', $context) || !$context['sender'] instanceof User) {
            return GuardResult::error($this->getName(), "Context is missing the 'sender' key, or it is not a User instance.", 'sender');
        }

        return GuardResult::pass($this->getName());
    }
}
