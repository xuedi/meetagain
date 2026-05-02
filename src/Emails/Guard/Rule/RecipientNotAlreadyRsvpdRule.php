<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Emails\EmailGuardCost;
use App\Emails\EmailGuardResult;
use App\Emails\EmailGuardRuleInterface;
use App\Entity\Event;
use App\Entity\User;

final readonly class RecipientNotAlreadyRsvpdRule implements EmailGuardRuleInterface
{
    public function getName(): string
    {
        return 'recipient_not_already_rsvpd';
    }

    public function getCost(): EmailGuardCost
    {
        return EmailGuardCost::InMemory;
    }

    public function evaluate(array $context): EmailGuardResult
    {
        $user = $context['user'] ?? null;
        if (!$user instanceof User) {
            return EmailGuardResult::error(
                $this->getName(),
                "Context is missing the 'user' key, or it is not a User instance.",
                'user',
            );
        }
        $event = $context['event'] ?? null;
        if (!$event instanceof Event) {
            return EmailGuardResult::error(
                $this->getName(),
                "Context is missing the 'event' key, or it is not an Event instance.",
                'event',
            );
        }

        if ($event->hasRsvp($user)) {
            return EmailGuardResult::skip(
                $this->getName(),
                "User already RSVP'd for this event.",
            );
        }

        return EmailGuardResult::pass($this->getName());
    }
}
