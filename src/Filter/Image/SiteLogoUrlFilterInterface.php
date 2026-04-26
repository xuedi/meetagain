<?php declare(strict_types=1);

namespace App\Filter\Image;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface SiteLogoUrlFilterInterface
{
    /**
     * Pre-SiteLogo override: returns a URL that overrides even an admin-configured SiteLogo,
     * or null to defer to the configured SiteLogo / fallback / default asset. Use sparingly -
     * only for cases where the context demands a specific logo regardless of admin preference
     * (e.g. domain-locked whitelabel tenants).
     */
    public function resolveSiteLogoUrl(): ?string;

    /**
     * Post-SiteLogo fallback: returns a URL only consulted when no SiteLogo is configured.
     * Use for "implicit" platform logos (e.g. main-host-group's logo as the platform's
     * default branding when no explicit SiteLogo has been uploaded). Return null to defer
     * to the next filter / the default asset.
     */
    public function resolveFallbackSiteLogoUrl(): ?string;
}
