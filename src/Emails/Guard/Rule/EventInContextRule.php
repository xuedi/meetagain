<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Entity\Event;

final readonly class EventInContextRule implements EmailGuardRuleInterface
{
    public function getName(): string
    {
        return 'event_in_context';
    }

    public function getCost(): EmailGuardCost
    {
        return EmailGuardCost::Free;
    }

    public function evaluate(array $context): EmailGuardResult
    {
        if (!array_key_exists('event', $context) || !$context['event'] instanceof Event) {
            return EmailGuardResult::error(
                $this->getName(),
                "Context is missing the 'event' key, or it is not an Event instance.",
                'event',
            );
        }

        return EmailGuardResult::pass($this->getName());
    }
}
