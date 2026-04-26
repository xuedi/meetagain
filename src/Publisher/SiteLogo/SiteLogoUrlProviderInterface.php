<?php declare(strict_types=1);

namespace App\Publisher\SiteLogo;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface SiteLogoUrlProviderInterface
{
    public function resolveSiteLogoUrl(): ?string;

    public function resolveFallbackSiteLogoUrl(): ?string;
}
