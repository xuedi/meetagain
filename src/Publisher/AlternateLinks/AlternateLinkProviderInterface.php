<?php declare(strict_types=1);

namespace App\Publisher\AlternateLinks;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;
use Symfony\Component\HttpFoundation\Request;

/**
 * Replaces the locale-to-URL map a page advertises as its hreflang cluster.
 * First non-null wins; return null to defer to the next provider.
 *
 * Unlike App\Filter\Language\AlternateLinkFilterInterface, which can only narrow the set of
 * locales, a provider may point a locale at an entirely different URL.
 */
#[AutoconfigureTag]
interface AlternateLinkProviderInterface
{
    /**
     * @param array<string, string> $localeUrls locale => absolute URL
     * @return array<string, string>|null
     */
    public function getAlternateLinks(array $localeUrls, Request $request): ?array;
}
