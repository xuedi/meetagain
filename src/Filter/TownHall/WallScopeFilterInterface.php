<?php declare(strict_types=1);

namespace App\Filter\TownHall;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the wall posts the town hall shows: null = no opinion, [] = block all,
 * [id, ...] = allow-list.
 */
#[AutoconfigureTag]
interface WallScopeFilterInterface
{
    /**
     * @return array<int>|null
     */
    public function getWallPostIdFilter(): ?array;
}
