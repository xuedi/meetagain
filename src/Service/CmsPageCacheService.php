<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\CmsBlockTypes;
use App\Repository\CmsBlockRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class CmsPageCacheService
{
    public function __construct(
        #[Autowire(service: 'cache.cms_page_cache')]
        private TagAwareCacheInterface $cache,
        private CmsBlockRepository $blockRepo,
    ) {}

    /**
     * Returns cached HTML for the given page, or null on a cache miss.
     *
     * @param array<int>|null $eventIds from EventFilterService
     */
    public function get(string $slug, string $locale, ?array $eventIds): ?string
    {
        $key = $this->buildKey($slug, $locale, $eventIds);
        $miss = false;

        // On a cache miss the callback is invoked; on a hit it is skipped.
        // We use a 1-second sentinel TTL so the miss marker does not pollute the cache.
        // store() uses beta=INF to overwrite it immediately with the real HTML.
        $html = $this->cache->get($key, static function (ItemInterface $item) use (&$miss): string {
            $miss = true;
            $item->expiresAfter(1);

            return '';
        });

        return $miss ? null : $html;
    }

    /**
     * Stores rendered HTML in the cache tagged with the page ID so it can be invalidated later.
     *
     * @param array<int>|null $eventIds from EventFilterService
     */
    public function store(string $slug, string $locale, ?array $eventIds, string $html, int $pageId): void
    {
        $key = $this->buildKey($slug, $locale, $eventIds);
        $tag = $this->buildTag($pageId);

        // beta=INF forces the callback to run even if a cached entry already exists,
        // replacing the short-lived miss sentinel written by get() with real content.
        $this->cache->get(
            $key,
            static function (ItemInterface $item) use ($html, $tag): string {
                $item->tag([$tag]);

                return $html;
            },
            \INF,
        );
    }

    /**
     * Invalidates all cached variants (all locales, all event-filter fingerprints) for a page.
     */
    public function invalidatePage(int $pageId): void
    {
        $this->cache->invalidateTags([$this->buildTag($pageId)]);
    }

    /**
     * Invalidates all menu location caches (CmsRepository + MenuService).
     * Must be called whenever menu location assignments change.
     */
    public function invalidateMenuCaches(): void
    {
        $this->cache->invalidateTags(['cms_menu']);
    }

    /**
     * Returns the IDs of all CMS pages that contain at least one EventTeaser block.
     *
     * @return array<int>
     */
    public function findEventTeaserPageIds(): array
    {
        return $this->blockRepo->findPageIdsWithType(CmsBlockTypes::EventTeaser);
    }

    /**
     * Produces a stable fingerprint of the active event filter so different groups
     * get separate cache entries when their event list differs.
     *
     * @param array<int>|null $eventIds
     */
    public function computeEventFilterFingerprint(?array $eventIds): string
    {
        if ($eventIds === null) {
            return 'global';
        }

        $sorted = $eventIds;
        sort($sorted);

        return md5(implode(',', $sorted));
    }

    private function buildKey(string $slug, string $locale, ?array $eventIds): string
    {
        $fingerprint = $this->computeEventFilterFingerprint($eventIds);

        return 'cms_page.' . $this->sanitizeKey($slug) . '.' . $locale . '.' . $fingerprint;
    }

    private function buildTag(int $pageId): string
    {
        return 'cms_page_' . $pageId;
    }

    private function sanitizeKey(string $slug): string
    {
        return preg_replace('/[^a-zA-Z0-9_.\-]/', '_', $slug) ?? $slug;
    }
}
