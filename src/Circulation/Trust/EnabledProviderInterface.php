<?php declare(strict_types=1);

namespace App\Circulation\Trust;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Answers whether circulation of an item type is scored by the trust module. The first
 * non-null answer wins; with no implementation circulation describes no trust context.
 */
#[AutoconfigureTag]
interface EnabledProviderInterface
{
    public function isTrustEnabled(string $itemType): ?bool;
}
