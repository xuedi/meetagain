<?php declare(strict_types=1);

namespace App\Filter\TownHall;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the events and members the town hall draws data from. Each getter returns
 * null = no opinion, [] = block all, [id, ...] = allow-list.
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
