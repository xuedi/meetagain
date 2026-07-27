<?php declare(strict_types=1);

namespace App\Item;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Produces a whole item list for the current request, so core can re-render the list region of any
 * type without knowing the plugin's service or template. Implementations must not memoise
 * getItemIds() - the facet counter evaluates it a second time with the facets suppressed.
 */
#[AutoconfigureTag]
interface ListProviderInterface
{
    /** Directory key of the owning plugin, matched against the active-plugin list. */
    public function getPluginKey(): string;

    /** Registry key for this item type. */
    public function getKey(): string;

    /**
     * Ids to list for the current request, in display order, after the full filter chain.
     *
     * @return list<int>
     */
    public function getItemIds(): array;

    /** The type's own list markup for the current request. */
    public function renderList(): string;

    /** Path of the type's clean list page. */
    public function getListUrl(): string;
}
