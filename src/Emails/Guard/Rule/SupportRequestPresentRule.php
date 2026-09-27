<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Entity\SupportRequest;
use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;

final readonly class SupportRequestPresentRule implements GuardRuleInterface
{
    public function getName(): string
    {
        return 'support_request_present';
    }

    public function getCost(): GuardCost
    {
        return GuardCost::Free;
    }

    public function evaluate(array $context): GuardResult
    {
        if (!array_key_exists('request', $context) || !$context['request'] instanceof SupportRequest) {
            return GuardResult::error($this->getName(), "Context is missing the 'request' key, or it is not a SupportRequest instance.", 'request');
        }

        return GuardResult::pass($this->getName());
    }
}
