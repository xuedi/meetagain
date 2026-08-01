<?php declare(strict_types=1);

namespace App\Item\Tag;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Registers one item type as taggable. Orthogonal to event-attachability
 * (TypeProviderInterface): a type may implement one, the other, or both.
 */
#[AutoconfigureTag]
interface TaggableTypeProviderInterface
{
    /** Directory key of the owning plugin, matched against the active-plugin list. */
    public function getPluginKey(): string;

    /** Registry key for this item type; the value stored in the tag and assignment tables. */
    public function getTypeKey(): string;

    /** Translation key for the type's label. */
    public function getLabelKey(): string;
}
