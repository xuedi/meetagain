<?php declare(strict_types=1);

namespace App\Publisher\UrlOwner;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Names the absolute origin (scheme and host, no trailing slash) that owns a route, or null to
 * defer to the next provider. The owner declares the canonical URL and is the only host whose
 * sitemap lists it; a route no provider claims belongs to whichever host serves it.
 */
#[AutoconfigureTag]
interface UrlOwnerProviderInterface
{
    /**
     * @param array<string, mixed> $parameters route parameters of the URL being resolved
     */
    public function getOwnerHost(string $route, array $parameters): ?string;
}
