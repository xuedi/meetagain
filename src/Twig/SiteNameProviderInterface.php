<?php declare(strict_types=1);

namespace App\Twig;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Supplies the site name for the current request; null defers to the next provider, then the configured value.
 */
#[AutoconfigureTag]
interface SiteNameProviderInterface
{
    public function getSiteName(): ?string;
}
