<?php declare(strict_types=1);

namespace App\Filter\Attribution;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the attributed images visible on the public attributions page. Implementations
 * compose with AND-intersection.
 */
#[AutoconfigureTag]
interface ImageAttributionFilterInterface
{
    /**
     * Higher priority runs first. Default: 0.
     */
    public function getPriority(): int;

    /**
     * @return array<int>|null null = no restriction (all images), [] = none, [id,...] = whitelist
     */
    public function getVisibleImageIdFilter(): ?array;
}
