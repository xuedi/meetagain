<?php declare(strict_types=1);

namespace App\Service\Seo;

use App\Entity\Cms;
use App\Entity\Event;
use App\Publisher\Sitemap\SitemapUrl;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\ValueObject\SitemapRow;

readonly class SitemapOverviewService
{
    /**
     * Stable canonical order so the section dropdown does not flicker as plugins activate/deactivate.
     * Sections present in the publisher output but not listed here are appended alphabetically.
     */
    private const array SECTION_ORDER = ['static', 'cms', 'events', 'items', 'members', 'groups', 'marketing'];

    public function __construct(
        private SitemapService $sitemapService,
        private EventRepository $eventRepository,
        private CmsRepository $cmsRepository,
    ) {}

    /**
     * @return list<SitemapRow>
     */
    public function getRows(): array
    {
        $sitemapUrls = $this->sitemapService->getUrls();
        $cmsById = $this->loadCmsByIds($this->collectMetaIds($sitemapUrls, 'cms_id'));
        $eventsById = $this->loadEventsByIds($this->collectMetaIds($sitemapUrls, 'event_id'));

        $rows = [];
        foreach ($sitemapUrls as $url) {
            $section = $url->section ?? 'other';
            $locale = $url->locale ?? '';

            $rows[] = new SitemapRow(
                section: $section,
                label: $this->resolveLabel($url, $cmsById, $eventsById),
                url: $url->loc,
                locale: $locale,
                lastmod: $url->lastmod?->format('Y-m-d') ?? '-',
                warnings: $this->collectWarnings($url, $section, $locale, $cmsById, $eventsById),
            );
        }

        return $rows;
    }

    /**
     * @param list<SitemapRow> $rows
     * @return list<string>
     */
    public function collectSections(array $rows): array
    {
        $present = [];
        foreach ($rows as $row) {
            $present[$row->section] = true;
        }
        $known = array_values(array_filter(self::SECTION_ORDER, static fn(string $s) => isset($present[$s])));
        $extras = array_keys(array_diff_key($present, array_flip(self::SECTION_ORDER)));
        sort($extras);

        return array_values(array_merge($known, $extras));
    }

    /**
     * @param list<SitemapRow> $rows
     */
    public function countWarnings(array $rows): int
    {
        $count = 0;
        foreach ($rows as $row) {
            if (!$row->hasWarnings()) {
                continue;
            }

            ++$count;
        }

        return $count;
    }

    /**
     * @param array<int, Cms> $cmsById
     * @param array<int, Event> $eventsById
     * @return list<string>
     */
    private function collectWarnings(SitemapUrl $url, string $section, string $locale, array $cmsById, array $eventsById): array
    {
        $warnings = [];

        if ($section === 'cms') {
            $page = $this->cmsFor($url, $cmsById);
            if ($page !== null && $locale !== '') {
                $title = $page->getPageTitle($locale);
                if ($title === null || $title === '') {
                    $warnings[] = 'admin_seo_sitemap.warning_missing_title';
                }
            }
        } elseif ($section === 'events') {
            $event = $this->eventFor($url, $eventsById);
            if ($event !== null) {
                if ($locale !== '' && $event->getTitle($locale) === '') {
                    $warnings[] = 'admin_seo_sitemap.warning_missing_title';
                }
                if ($event->getPreviewImage() === null) {
                    $warnings[] = 'admin_seo_sitemap.warning_no_preview_image';
                }
            }
        }

        return $warnings;
    }

    /**
     * @param array<SitemapUrl> $sitemapUrls
     * @return list<int>
     */
    private function collectMetaIds(array $sitemapUrls, string $metaKey): array
    {
        $ids = [];
        foreach ($sitemapUrls as $url) {
            if (!isset($url->meta[$metaKey])) {
                continue;
            }
            $ids[(int) $url->meta[$metaKey]] = true;
        }

        return array_keys($ids);
    }

    /**
     * @param list<int> $ids
     * @return array<int, Cms>
     */
    private function loadCmsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $byId = [];
        foreach ($this->cmsRepository->findByIds($ids) as $page) {
            $id = $page->getId();
            if ($id !== null) {
                $byId[$id] = $page;
            }
        }

        return $byId;
    }

    /**
     * @param list<int> $ids
     * @return array<int, Event>
     */
    private function loadEventsByIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }
        $byId = [];
        foreach ($this->eventRepository->findByIds($ids) as $event) {
            $id = $event->getId();
            if ($id !== null) {
                $byId[$id] = $event;
            }
        }

        return $byId;
    }

    /**
     * @param array<int, Cms> $cmsById
     */
    private function cmsFor(SitemapUrl $url, array $cmsById): ?Cms
    {
        $cmsId = isset($url->meta['cms_id']) ? (int) $url->meta['cms_id'] : null;

        return $cmsId !== null ? $cmsById[$cmsId] ?? null : null;
    }

    /**
     * @param array<int, Event> $eventsById
     */
    private function eventFor(SitemapUrl $url, array $eventsById): ?Event
    {
        $eventId = isset($url->meta['event_id']) ? (int) $url->meta['event_id'] : null;

        return $eventId !== null ? $eventsById[$eventId] ?? null : null;
    }

    /**
     * @param array<int, Cms> $cmsById
     * @param array<int, Event> $eventsById
     */
    private function resolveLabel(SitemapUrl $url, array $cmsById, array $eventsById): string
    {
        $section = $url->section;
        $locale = $url->locale ?? '';

        if ($section === 'cms') {
            $page = $this->cmsFor($url, $cmsById);
            $title = $page !== null && $locale !== '' ? $page->getPageTitle($locale) : null;
            if ($title !== null && $title !== '') {
                return $title;
            }
            $slug = isset($url->meta['slug']) ? (string) $url->meta['slug'] : null;
            if ($slug !== null && $slug !== '') {
                return $slug;
            }
        }

        if ($section === 'events') {
            $event = $this->eventFor($url, $eventsById);
            if ($event !== null && $locale !== '') {
                $title = $event->getTitle($locale);
                if ($title !== '') {
                    return $title;
                }
            }
            $eventId = isset($url->meta['event_id']) ? (int) $url->meta['event_id'] : null;
            if ($eventId !== null) {
                return sprintf('Event #%d', $eventId);
            }
        }

        if ($section === 'groups' && isset($url->meta['group_name'])) {
            return (string) $url->meta['group_name'];
        }

        if (isset($url->meta['title']) && $url->meta['title'] !== '') {
            return (string) $url->meta['title'];
        }

        if (isset($url->meta['route'])) {
            return (string) $url->meta['route'];
        }

        $path = parse_url($url->loc, PHP_URL_PATH);
        if (is_string($path)) {
            return $path;
        }

        return $url->loc;
    }
}
