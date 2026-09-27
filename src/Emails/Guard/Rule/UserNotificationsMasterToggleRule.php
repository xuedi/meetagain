<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Entity\User;
use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;

final readonly class UserNotificationsMasterToggleRule implements GuardRuleInterface
{
    public function __construct(
        private string $recipientKey = 'user',
    ) {}

    public function getName(): string
    {
        return 'user_notifications_master_toggle';
    }

    public function getCost(): GuardCost
    {
        return GuardCost::InMemory;
    }

    public function evaluate(array $context): GuardResult
    {
        $user = $context[$this->recipientKey] ?? null;
        if (!$user instanceof User) {
            return GuardResult::error(
                $this->getName(),
                sprintf("Context is missing the '%s' key, or it is not a User instance.", $this->recipientKey),
                $this->recipientKey,
            );
        }

        if (!$user->isNotification()) {
            return GuardResult::skip($this->getName(), 'User has globally disabled email notifications.');
        }

        return GuardResult::pass($this->getName());
    }
}
