<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Entity\User;

final readonly class NotificationToggleEnabledRule implements EmailGuardRuleInterface
{
    public function __construct(
        private string $toggle,
        private string $recipientKey = 'user',
    ) {}

    public function getName(): string
    {
        return 'notification_toggle_enabled:' . $this->toggle;
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

        if (!$user->getNotificationSettings()->isActive($this->toggle)) {
            return EmailGuardResult::skip(
                $this->getName(),
                sprintf("User has disabled the '%s' notification preference.", $this->toggle),
            );
        }

        return EmailGuardResult::pass($this->getName());
    }
}
