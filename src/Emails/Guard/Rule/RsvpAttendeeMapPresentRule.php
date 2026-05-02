<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;

final readonly class RsvpAttendeeMapPresentRule implements EmailGuardRuleInterface
{
    public function getName(): string
    {
        return 'rsvp_attendee_map_present';
    }

    public function getCost(): EmailGuardCost
    {
        return EmailGuardCost::Free;
    }

    public function evaluate(array $context): EmailGuardResult
    {
        if (!array_key_exists('attendeeMap', $context) || $context['attendeeMap'] === null) {
            return EmailGuardResult::error(
                $this->getName(),
                "Context is missing the 'attendeeMap' key.",
                'attendeeMap',
            );
        }

        return EmailGuardResult::pass($this->getName());
    }
}
