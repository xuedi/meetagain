<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Registers one item type as categorizable and/or taggable. This axis is orthogonal to
 * event-attachability (ItemTypeProviderInterface): a type may implement one, the other, or both.
 * The registry keys providers by getTypeKey() and shows only those whose owning plugin is active.
 * getTaxonomy() returns the type's own scope-resolved definitions, so core never reads a plugin
 * config class and per-scope resolution rides on the plugin's own settings resolver.
 */
#[AutoconfigureTag]
interface CategorizableTypeProviderInterface
{
    /** Directory key of the owning plugin, matched against the active-plugin list. */
    public function getPluginKey(): string;

    /** Registry key for this item type; the value stored in the assignment tables' item_type column. */
    public function getTypeKey(): string;

    /** Translation key for the type's label. */
    public function getLabelKey(): string;

    public function supportsCategories(): bool;

    public function supportsTags(): bool;

    /** The type's effective, scope-resolved taxonomy definitions for the current request. */
    public function getTaxonomy(): TaxonomyConfig;
}
