<?php declare(strict_types=1);

namespace App\Filter\Event;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for user-scoped event filters on the profile page.
 * Plugins implement this to restrict which events appear in a user's profile view.
 *
 * Multiple filters can be registered — composed with AND logic via EventFilterService.
 */
#[AutoconfigureTag]
interface UserProfileEventFilterInterface
{
    /**
     * Get allowed event IDs for the given user's profile view.
     *
     * @return array<int>|null Returns:
     *         - null: No filtering (allow all events)
     *         - array<int>: Only these event IDs are allowed
     *         - []: No events allowed (empty result)
     */
    public function getEventIdFilterForUser(User $user): ?array;
}
