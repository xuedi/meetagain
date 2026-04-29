<?php declare(strict_types=1);

namespace App\Filter\Event;

use App\Entity\Event;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Vetoes follower-driven event notifications per (recipient, attendee, event) triple.
 * Returning false drops that recipient/attendee pair from the notification.
 */
#[AutoconfigureTag]
interface FollowerEventNotificationFilterInterface
{
    public function isFollowerAllowed(User $recipient, User $attendee, Event $event): bool;
}
