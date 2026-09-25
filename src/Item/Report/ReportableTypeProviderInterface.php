<?php declare(strict_types=1);

namespace App\Item\Report;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Opts one item type into the report form any visitor can reach. A type without an
 * implementation cannot be reported; visibility still runs through the item filter chain.
 */
#[AutoconfigureTag]
interface ReportableTypeProviderInterface
{
    /** Directory key of the owning plugin, matched against the active-plugin list. */
    public function getPluginKey(): string;

    /** Registry key for this item type; the value stored in ItemReport::itemType. */
    public function getTypeKey(): string;

    /** Display label of one item; null when the item does not exist. */
    public function getItemLabel(int $itemId): ?string;

    /** Absolute path of the item's public page. */
    public function getItemPath(int $itemId): string;
}
