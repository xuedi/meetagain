<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Entity\SupportRequest;

final readonly class SupportRequestPresentRule implements EmailGuardRuleInterface
{
    public function getName(): string
    {
        return 'support_request_present';
    }

    public function getCost(): EmailGuardCost
    {
        return EmailGuardCost::Free;
    }

    public function evaluate(array $context): EmailGuardResult
    {
        if (!array_key_exists('request', $context) || !$context['request'] instanceof SupportRequest) {
            return EmailGuardResult::error(
                $this->getName(),
                "Context is missing the 'request' key, or it is not a SupportRequest instance.",
                'request',
            );
        }

        return EmailGuardResult::pass($this->getName());
    }
}
