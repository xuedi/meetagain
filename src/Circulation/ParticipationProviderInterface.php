<?php declare(strict_types=1);

namespace App\Circulation;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Answers whether an item type circulates right now. The first non-null answer wins;
 * with no implementation nothing circulates.
 */
#[AutoconfigureTag]
interface ParticipationProviderInterface
{
    public function isEnabled(string $itemType): ?bool;
}
