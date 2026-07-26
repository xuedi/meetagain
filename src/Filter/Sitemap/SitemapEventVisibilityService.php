<?php declare(strict_types=1);

namespace App\Filter\Sitemap;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class SitemapEventVisibilityService
{
    /**
     * @param iterable<SitemapEventVisibilityFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(SitemapEventVisibilityFilterInterface::class)]
        private iterable $filters,
    ) {}

    public function shouldEmitEvents(): bool
    {
        foreach ($this->filters as $filter) {
            if (!$filter->shouldEmitEvents()) {
                return false;
            }
        }

        return true;
    }
}
