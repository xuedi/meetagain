<?php declare(strict_types=1);

namespace App\Filter\Attribution;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Restricts which attributed images are visible in the current context.
 *
 * Plugins can implement this to narrow the images shown on the public attributions
 * page. Multiple filters compose with AND (intersection): an image must pass all of
 * them. With no implementations registered, nothing is restricted and all attributed
 * images are visible.
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
