<?php declare(strict_types=1);

namespace App\Item\Tag;

use App\Entity\ItemTag;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Called right after a tag row is created, so an implementation can claim it for whatever it
 * scopes the vocabulary by. Runs before the tag is first read back.
 */
#[AutoconfigureTag]
interface CreationHandlerInterface
{
    public function onTagCreated(ItemTag $tag): void;
}
