<?php declare(strict_types=1);

namespace App\Filter\Sitemap;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Hook for suppressing event URLs from the sitemap in specific contexts.
 *
 * The default core behavior is to emit events. The multisite plugin registers
 * an implementation that returns false on whitelabel hosts, where events are
 * platform-canonical and must not appear in the local sitemap.
 *
 * If any registered filter returns false, events are suppressed.
 */
#[AutoconfigureTag]
interface SitemapEventVisibilityFilterInterface
{
    public function shouldEmitEvents(): bool;
}
