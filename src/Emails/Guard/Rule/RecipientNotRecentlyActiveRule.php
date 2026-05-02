<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Entity\User;
use DateInterval;
use Symfony\Component\Clock\ClockInterface;

final readonly class RecipientNotRecentlyActiveRule implements EmailGuardRuleInterface
{
    public function __construct(
        private ClockInterface $clock,
        private DateInterval $window,
        private string $recipientKey = 'recipient',
    ) {}

    public function getName(): string
    {
        return 'recipient_not_recently_active';
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

        $threshold = $this->clock->now()->sub($this->window);
        $lastLogin = $user->getLastLogin();
        if ($lastLogin !== null && $lastLogin > $threshold) {
            return EmailGuardResult::skip(
                $this->getName(),
                'User logged in within the configured recent-activity window.',
            );
        }

        return EmailGuardResult::pass($this->getName());
    }
}
