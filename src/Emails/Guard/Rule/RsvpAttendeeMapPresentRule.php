<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;

final readonly class RsvpAttendeeMapPresentRule implements GuardRuleInterface
{
    public function getName(): string
    {
        return 'rsvp_attendee_map_present';
    }

    public function getCost(): GuardCost
    {
        return GuardCost::Free;
    }

    public function evaluate(array $context): GuardResult
    {
        if (!array_key_exists('attendeeMap', $context) || $context['attendeeMap'] === null) {
            return GuardResult::error($this->getName(), "Context is missing the 'attendeeMap' key.", 'attendeeMap');
        }

        return GuardResult::pass($this->getName());
    }
}
