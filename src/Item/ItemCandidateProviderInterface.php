<?php declare(strict_types=1);

namespace App\Item;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Supplies ranked candidate item ids for an item type, highest-ranked first.
 * Union chain: the attach control and the voting subsystem read every provider's candidates.
 */
#[AutoconfigureTag]
interface ItemCandidateProviderInterface
{
    /** @return list<int> ranked candidate item ids for the given type (may be empty) */
    public function getCandidateItemIds(string $itemType): array;
}
