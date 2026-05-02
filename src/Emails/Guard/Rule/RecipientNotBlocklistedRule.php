<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Entity\User;
use App\Service\Email\BlocklistCheckerInterface;

final readonly class RecipientNotBlocklistedRule implements EmailGuardRuleInterface
{
    public function __construct(
        private BlocklistCheckerInterface $blocklist,
        private string $recipientKey = 'user',
    ) {}

    public function getName(): string
    {
        return 'recipient_not_blocklisted';
    }

    public function getCost(): EmailGuardCost
    {
        return EmailGuardCost::Database;
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

        if ($this->blocklist->isBlocked((string) $user->getEmail())) {
            return EmailGuardResult::skip(
                $this->getName(),
                'Recipient address is on the global email blocklist.',
            );
        }

        return EmailGuardResult::pass($this->getName());
    }
}
