<?php declare(strict_types=1);

namespace App\Publisher\OgImage;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Plugins can implement this interface to override the default OG image
 * emitted in the page <head>. Returning null means "I don't claim this
 * slot" and lets the next provider, then the system default, win.
 */
#[AutoconfigureTag]
interface OgImageProviderInterface
{
    public function resolveOgImage(): ?ResolvedOgImage;
}
