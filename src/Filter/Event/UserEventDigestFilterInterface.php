<?php declare(strict_types=1);

namespace App\Filter\Event;

use App\Entity\Event;
use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Filters the event list for a specific user in non-request contexts (e.g. cron).
 * Without any registered implementation, all events pass through unfiltered.
 */
#[AutoconfigureTag]
interface UserEventDigestFilterInterface
{
    /**
     * @param array<Event> $events
     * @return array<Event>
     */
    public function filterForUser(array $events, User $user): array;
}
