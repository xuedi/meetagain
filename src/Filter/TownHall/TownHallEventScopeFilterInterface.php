<?php declare(strict_types=1);

namespace App\Filter\TownHall;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the set of events and members the town-hall draws data from.
 *
 * Implementations return either:
 *   null  - no opinion
 *   []    - block: zero rows
 *   array - intersect
 */
#[AutoconfigureTag]
interface TownHallEventScopeFilterInterface
{
    /**
     * @return array<int>|null
     */
    public function getEventIdFilter(): ?array;

    /**
     * @return array<int>|null
     */
    public function getUserIdFilter(): ?array;
}
