<?php declare(strict_types=1);

namespace App\Filter\Sitemap;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * If any registered implementation returns false, event URLs are dropped from the sitemap.
 */
#[AutoconfigureTag]
interface SitemapEventVisibilityFilterInterface
{
    public function shouldEmitEvents(): bool;
}
