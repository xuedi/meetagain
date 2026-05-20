<?php declare(strict_types=1);

namespace App\Filter\TownHall;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the set of wall posts the town-hall shows.
 *
 * Implementations return either:
 *   null  - no opinion: this filter does not constrain
 *   []    - block: zero posts visible
 *   array - intersect with other filters' results
 */
#[AutoconfigureTag]
interface WallScopeFilterInterface
{
    /**
     * @return array<int>|null
     */
    public function getWallPostIdFilter(): ?array;
}
