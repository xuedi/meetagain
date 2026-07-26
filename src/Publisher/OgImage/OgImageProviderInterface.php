<?php declare(strict_types=1);

namespace App\Publisher\OgImage;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Claims the OG image emitted in the page head; null defers to the next provider, then the default.
 */
#[AutoconfigureTag]
interface OgImageProviderInterface
{
    public function resolveOgImage(): ?ResolvedOgImage;
}
