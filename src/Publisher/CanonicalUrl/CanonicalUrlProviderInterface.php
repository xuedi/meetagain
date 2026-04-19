<?php declare(strict_types=1);

namespace App\Publisher\CanonicalUrl;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Interface for canonical URL providers.
 * Plugins can implement this to override the canonical URL for a given request.
 *
 * Return null to indicate no override — the default self-referencing canonical
 * (ConfigService::getHost() + current path) will be used.
 *
 * Primary use case: multisite plugin maps whitelabel domain URLs to their
 * canonical platform equivalents.
 */
#[AutoconfigureTag]
interface CanonicalUrlProviderInterface
{
    /**
     * Return the canonical URL for the given request, or null to defer to the next provider.
     */
    public function getCanonicalUrl(string $currentUrl, Request $request): ?string;
}
