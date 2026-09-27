<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Entity\User;
use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;

final readonly class RecipientKeyUserPresentRule implements GuardRuleInterface
{
    public function getName(): string
    {
        return 'recipient_key_user_present';
    }

    public function getCost(): GuardCost
    {
        return GuardCost::Free;
    }

    public function evaluate(array $context): GuardResult
    {
        if (!array_key_exists('recipient', $context) || !$context['recipient'] instanceof User) {
            return GuardResult::error($this->getName(), "Context is missing the 'recipient' key, or it is not a User instance.", 'recipient');
        }

        return GuardResult::pass($this->getName());
    }
}
