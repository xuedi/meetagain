<?php declare(strict_types=1);

namespace App\Publisher\CanonicalUrl;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

#[AutoconfigureTag]
interface CanonicalUrlProviderInterface
{
    /**
     * Return the canonical URL for the request, or null to defer to the next provider.
     */
    public function getCanonicalUrl(string $currentUrl, Request $request): ?string;
}
