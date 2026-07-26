<?php declare(strict_types=1);

namespace App\Filter\Event;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the events shown on a user's profile. Implementations compose with AND-intersection.
 */
#[AutoconfigureTag]
interface UserProfileEventFilterInterface
{
    /**
     * @return array<int>|null null = no opinion, [] = block all, [id, ...] = allow-list
     */
    public function getEventIdFilterForUser(User $user): ?array;
}
