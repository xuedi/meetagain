<?php declare(strict_types=1);

namespace Plugin\Voting\Filter;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the visible poll ids. Implementations compose with AND-intersection;
 * null = no opinion, [] = block all, [id, ...] = allow-list.
 */
#[AutoconfigureTag]
interface PollFilterInterface
{
    /** @return int[]|null */
    public function getAllowedPollIds(): ?array;
}
