<?php declare(strict_types=1);

namespace App\Item\Tag;

use App\Entity\ItemTag;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Called right before a tag row is removed, so an implementation can drop whatever it attached
 * to that row. The tag is still readable; anything left pointing at it outlives the flush.
 */
#[AutoconfigureTag]
interface DeletionHandlerInterface
{
    public function onTagDeleted(ItemTag $tag): void;
}
