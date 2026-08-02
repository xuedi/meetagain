<?php declare(strict_types=1);

namespace App\Filter\TownHall;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the topics the town hall forum shows: null = no opinion, [] = block all,
 * [id, ...] = allow-list.
 */
#[AutoconfigureTag]
interface TopicScopeFilterInterface
{
    /**
     * @return array<int>|null
     */
    public function getTopicIdFilter(): ?array;
}
