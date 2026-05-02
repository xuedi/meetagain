<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Entity\User;

final readonly class UserNotificationsMasterToggleRule implements EmailGuardRuleInterface
{
    public function __construct(
        private string $recipientKey = 'user',
    ) {}

    public function getName(): string
    {
        return 'user_notifications_master_toggle';
    }

    public function getCost(): EmailGuardCost
    {
        return EmailGuardCost::InMemory;
    }

    public function evaluate(array $context): EmailGuardResult
    {
        $user = $context[$this->recipientKey] ?? null;
        if (!$user instanceof User) {
            return EmailGuardResult::error(
                $this->getName(),
                sprintf("Context is missing the '%s' key, or it is not a User instance.", $this->recipientKey),
                $this->recipientKey,
            );
        }

        if (!$user->isNotification()) {
            return EmailGuardResult::skip(
                $this->getName(),
                'User has globally disabled email notifications.',
            );
        }

        return EmailGuardResult::pass($this->getName());
    }
}
