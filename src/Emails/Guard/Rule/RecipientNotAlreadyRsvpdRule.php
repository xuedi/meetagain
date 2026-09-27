<?php declare(strict_types=1);

namespace App\Emails\Guard\Rule;

use App\Entity\Event;
use App\Entity\User;
use Module\Email\Contract\GuardCost;
use Module\Email\Contract\GuardResult;
use Module\Email\Contract\GuardRuleInterface;

final readonly class RecipientNotAlreadyRsvpdRule implements GuardRuleInterface
{
    public function getName(): string
    {
        return 'recipient_not_already_rsvpd';
    }

    public function getCost(): GuardCost
    {
        return GuardCost::InMemory;
    }

    public function evaluate(array $context): GuardResult
    {
        $user = $context['user'] ?? null;
        if (!$user instanceof User) {
            return GuardResult::error($this->getName(), "Context is missing the 'user' key, or it is not a User instance.", 'user');
        }
        $event = $context['event'] ?? null;
        if (!$event instanceof Event) {
            return GuardResult::error($this->getName(), "Context is missing the 'event' key, or it is not an Event instance.", 'event');
        }

        if ($event->hasRsvp($user)) {
            return GuardResult::skip($this->getName(), "User already RSVP'd for this event.");
        }

        return GuardResult::pass($this->getName());
    }
}
