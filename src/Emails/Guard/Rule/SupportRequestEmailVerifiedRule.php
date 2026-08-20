<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Entity\SupportRequest;

final readonly class SupportRequestEmailVerifiedRule implements EmailGuardRuleInterface
{
    public function getName(): string
    {
        return 'support_request_email_verified';
    }

    public function getCost(): EmailGuardCost
    {
        return EmailGuardCost::Free;
    }

    public function evaluate(array $context): EmailGuardResult
    {
        if (!array_key_exists('request', $context) || !$context['request'] instanceof SupportRequest) {
            return EmailGuardResult::error($this->getName(), "Context is missing the 'request' key, or it is not a SupportRequest instance.", 'request');
        }

        if (!$context['request']->isEmailVerified()) {
            return EmailGuardResult::skip($this->getName(), 'The requester address was never confirmed through the double opt-in.');
        }

        return EmailGuardResult::pass($this->getName());
    }
}
