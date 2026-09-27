<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Entity\User;
use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;

final readonly class NotificationToggleEnabledRule implements GuardRuleInterface
{
    public function __construct(
        private string $toggle,
        private string $recipientKey = 'user',
    ) {}

    public function getName(): string
    {
        return 'notification_toggle_enabled:' . $this->toggle;
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

        if (!$user->getNotificationSettings()->isActive($this->toggle)) {
            return GuardResult::skip($this->getName(), sprintf("User has disabled the '%s' notification preference.", $this->toggle));
        }

        return GuardResult::pass($this->getName());
    }
}
