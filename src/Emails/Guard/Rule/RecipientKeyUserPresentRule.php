<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Entity\User;

/**
 * Used when the recipient lives under the 'recipient' key (NotificationMessage), not 'user'.
 */
final readonly class RecipientKeyUserPresentRule implements EmailGuardRuleInterface
{
    public function getName(): string
    {
        return 'recipient_key_user_present';
    }

    public function getCost(): EmailGuardCost
    {
        return EmailGuardCost::Free;
    }

    public function evaluate(array $context): EmailGuardResult
    {
        if (!array_key_exists('recipient', $context) || !$context['recipient'] instanceof User) {
            return EmailGuardResult::error(
                $this->getName(),
                "Context is missing the 'recipient' key, or it is not a User instance.",
                'recipient',
            );
        }

        return EmailGuardResult::pass($this->getName());
    }
}
