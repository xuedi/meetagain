<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Entity\User;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;

final readonly class RecipientNotBlocklistedRule implements GuardRuleInterface
{
    public function __construct(
        private BlocklistInterface $blocklist,
        private string $recipientKey = 'user',
    ) {}

    public function getName(): string
    {
        return 'recipient_not_blocklisted';
    }

    public function getCost(): GuardCost
    {
        return GuardCost::Database;
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

        if ($this->blocklist->isBlocked((string) $user->getEmail())) {
            return GuardResult::skip($this->getName(), 'Recipient address is on the global email blocklist.');
        }

        return GuardResult::pass($this->getName());
    }
}
