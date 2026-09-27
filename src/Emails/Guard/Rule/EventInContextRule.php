<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Entity\Event;
use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;

final readonly class EventInContextRule implements GuardRuleInterface
{
    public function getName(): string
    {
        return 'event_in_context';
    }

    public function getCost(): GuardCost
    {
        return GuardCost::Free;
    }

    public function evaluate(array $context): GuardResult
    {
        if (!array_key_exists('event', $context) || !$context['event'] instanceof Event) {
            return GuardResult::error($this->getName(), "Context is missing the 'event' key, or it is not an Event instance.", 'event');
        }

        return GuardResult::pass($this->getName());
    }
}
