<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Publisher\Sitemap\SitemapUrl;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Service\Config\LanguageService;
use App\Service\Seo\SitemapService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system')]
final class SitemapController extends AbstractSettingsController implements AdminNavigationInterface, AdminTabsInterface
{
    /**
     * Stable canonical order so the section dropdown does not flicker as plugins activate/deactivate.
     * Sections present in the publisher output but not listed here are appended alphabetically.
     */
    private const array SECTION_ORDER = ['static', 'cms', 'events', 'members', 'groups', 'marketing'];

    public function __construct(
        TranslatorInterface $translator,
        private readonly SitemapService $sitemapService,
        private readonly LanguageService $languageService,
        private readonly EventRepository $eventRepository,
        private readonly CmsRepository $cmsRepository,
    ) {
        parent::__construct($translator, 'sitemap');
    }

    #[Route('/sitemap', name: 'app_admin_system_sitemap')]
    public function sitemap(Request $request): Response
    {
        $locales = $this->languageService->getFilteredEnabledCodes();
        $sitemapUrls = $this->sitemapService->getUrls();
        $allUrls = $this->buildRows($sitemapUrls);
        $sections = $this->collectSections($sitemapUrls);

        $localeFilter = $request->query->getString('locale');
        if ($localeFilter !== '' && !in_array($localeFilter, $locales, true)) {
            $localeFilter = '';
        }

        $sectionFilter = $request->query->getString('section');
        if ($sectionFilter !== '' && !in_array($sectionFilter, $sections, true)) {
            $sectionFilter = '';
        }

        $warningsOnly = $request->query->getBoolean('warnings');

        $rows = array_filter(
            $allUrls,
            static fn(array $row): bool => (
                ($localeFilter === '' || $row['locale'] === $localeFilter)
                && ($sectionFilter === '' || $row['section'] === $sectionFilter)
                && (!$warningsOnly || count($row['warnings']) > 0)
            ),
        );

        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', count($allUrls), $this->translator->trans('admin_system_sitemap.summary_urls'))),
        ];
        if ($localeFilter !== '' || $sectionFilter !== '' || $warningsOnly) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($rows),
                $this->translator->trans('admin_system_sitemap.summary_shown'),
            ));
        }
        $warningsTotal = $this->countWarnings($allUrls);
        if ($warningsTotal > 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-warning is-medium"><strong>%d</strong>&nbsp;%s</span>',
                $warningsTotal,
                $this->translator->trans('admin_system_sitemap.summary_with_warnings'),
            ));
        }

        $adminTop = new AdminTop(info: $info, actions: [
            $this->buildWarningsToggle($warningsOnly, $localeFilter, $sectionFilter),
            $this->buildSectionDropdown($sectionFilter, $localeFilter, $warningsOnly, $allUrls, $sections),
            $this->buildLocaleDropdown($localeFilter, $sectionFilter, $warningsOnly, $allUrls, $locales),
        ]);

        return $this->render('admin/system/sitemap/index.html.twig', [
            'active' => 'system',
            'urls' => array_values($rows),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    /**
     * @param array<SitemapUrl> $sitemapUrls
     *
     * @return list<array{section: string, label: string, url: string, locale: string, lastmod: string, warnings: list<string>}>
     */
    private function buildRows(array $sitemapUrls): array
    {
        $cmsById = $this->loadCmsByIds($this->collectMetaIds($sitemapUrls, 'cms_id'));
        $eventsById = $this->loadEventsByIds($this->collectMetaIds($sitemapUrls, 'event_id'));
        $missingTitle = $this->translator->trans('admin_system_sitemap.warning_missing_title');
        $noPreview = $this->translator->trans('admin_system_sitemap.warning_no_preview_image');

        $rows = [];
        foreach ($sitemapUrls as $url) {
            $section = $url->section ?? 'other';
            $locale = $url->locale ?? '';
            $warnings = [];

            $label = $this->resolveLabel($url, $cmsById, $eventsById);

            if ($section === 'cms') {
                $cmsId = isset($url->meta['cms_id']) ? (int) $url->meta['cms_id'] : null;
                $page = $cmsId !== null ? $cmsById[$cmsId] ?? null : null;
                if ($page !== null && $locale !== '') {
                    $title = $page->getPageTitle($locale);
                    if ($title === null || $title === '') {
                        $warnings[] = $missingTitle;
                    }
                }
            } elseif ($section === 'events') {
                $eventId = isset($url->meta['event_id']) ? (int) $url->meta['event_id'] : null;
                $event = $eventId !== null ? $eventsById[$eventId] ?? null : null;
                if ($event !== null) {
                    if ($locale !== '' && $event->getTitle($locale) === '') {
                        $warnings[] = $missingTitle;
                    }
                    if ($event->getPreviewImage() === null) {
                        $warnings[] = $noPreview;
                    }
                }
            }

            $rows[] = [
                'section' => $section,
                'label' => $label,
                'url' => $url->loc,
                'locale' => $locale,
                'lastmod' => $url->lastmod?->format('Y-m-d') ?? '-',
                'warnings' => $warnings,
            ];
        }

        return $rows;
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
     * @return array<int, \App\Entity\Cms>
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
     * @return array<int, \App\Entity\Event>
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
     * @param array<int, \App\Entity\Cms> $cmsById
     * @param array<int, \App\Entity\Event> $eventsById
     */
    private function resolveLabel(SitemapUrl $url, array $cmsById, array $eventsById): string
    {
        $section = $url->section;
        $locale = $url->locale ?? '';

        if ($section === 'cms') {
            $cmsId = isset($url->meta['cms_id']) ? (int) $url->meta['cms_id'] : null;
            $page = $cmsId !== null ? $cmsById[$cmsId] ?? null : null;
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
            $eventId = isset($url->meta['event_id']) ? (int) $url->meta['event_id'] : null;
            $event = $eventId !== null ? $eventsById[$eventId] ?? null : null;
            if ($event !== null && $locale !== '') {
                $title = $event->getTitle($locale);
                if ($title !== '') {
                    return $title;
                }
            }
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

    /**
     * @param array<SitemapUrl> $sitemapUrls
     * @return list<string>
     */
    private function collectSections(array $sitemapUrls): array
    {
        $present = [];
        foreach ($sitemapUrls as $url) {
            if ($url->section === null) {
                continue;
            }
            $present[$url->section] = true;
        }
        $known = array_values(array_filter(self::SECTION_ORDER, static fn($s) => isset($present[$s])));
        $extras = array_keys(array_diff_key($present, array_flip(self::SECTION_ORDER)));
        sort($extras);

        return array_values(array_merge($known, $extras));
    }

    private function countWarnings(array $urls): int
    {
        $count = 0;
        foreach ($urls as $url) {
            if (count($url['warnings']) <= 0) {
                continue;
            }

            ++$count;
        }

        return $count;
    }

    /**
     * @param list<string> $sections
     */
    private function buildSectionDropdown(string $current, string $locale, bool $warnings, array $allUrls, array $sections): AdminTopActionDropdown
    {
        $base = $this->buildBaseParams($locale, '', $warnings);

        $options = [
            new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_system_sitemap.section_filter_all'),
                target: $this->generateUrl('app_admin_system_sitemap', $base),
                isActive: $current === '',
            ),
        ];
        foreach ($sections as $section) {
            $count = 0;
            foreach ($allUrls as $row) {
                if (!($row['section'] === $section && ($locale === '' || $row['locale'] === $locale))) {
                    continue;
                }

                ++$count;
            }
            $options[] = new AdminTopActionDropdownOption(
                label: $this->labelForSection($section),
                target: $this->generateUrl('app_admin_system_sitemap', ['section' => $section] + $base),
                isActive: $current === $section,
                count: $count,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_system_sitemap.section_filter_label'),
                $current === '' ? $this->translator->trans('admin_system_sitemap.section_filter_all') : $this->labelForSection($current),
            ),
            options: $options,
            icon: 'layer-group',
        );
    }

    private function labelForSection(string $section): string
    {
        $key = 'admin_system_sitemap.section_' . $section;
        $translated = $this->translator->trans($key);
        if ($translated !== $key) {
            return $translated;
        }

        return ucfirst($section);
    }

    /**
     * @param list<string> $locales
     */
    private function buildLocaleDropdown(string $current, string $section, bool $warnings, array $allUrls, array $locales): AdminTopActionDropdown
    {
        $base = $this->buildBaseParams('', $section, $warnings);

        $options = [
            new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_system_sitemap.locale_filter_all'),
                target: $this->generateUrl('app_admin_system_sitemap', $base),
                isActive: $current === '',
            ),
        ];
        foreach ($locales as $locale) {
            $count = 0;
            foreach ($allUrls as $row) {
                if (!($row['locale'] === $locale && ($section === '' || $row['section'] === $section))) {
                    continue;
                }

                ++$count;
            }
            $options[] = new AdminTopActionDropdownOption(
                label: strtoupper($locale),
                target: $this->generateUrl('app_admin_system_sitemap', ['locale' => $locale] + $base),
                isActive: $current === $locale,
                count: $count,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_system_sitemap.locale_filter_label'),
                $current === '' ? $this->translator->trans('admin_system_sitemap.locale_filter_all') : strtoupper($current),
            ),
            options: $options,
            icon: 'language',
        );
    }

    private function buildWarningsToggle(bool $current, string $locale, string $section): AdminTopActionButton
    {
        $params = $this->buildBaseParams($locale, $section, !$current);

        return new AdminTopActionButton(
            label: $this->translator->trans($current ? 'admin_system_sitemap.button_hide_warnings' : 'admin_system_sitemap.button_show_warnings'),
            target: $this->generateUrl('app_admin_system_sitemap', $params),
            icon: $current ? 'filter-circle-xmark' : 'filter',
            variant: $current ? 'is-warning' : null,
        );
    }

    /**
     * @return array<string, string|int>
     */
    private function buildBaseParams(string $locale, string $section, bool $warnings): array
    {
        $params = [];
        if ($locale !== '') {
            $params['locale'] = $locale;
        }
        if ($section !== '') {
            $params['section'] = $section;
        }
        if ($warnings) {
            $params['warnings'] = 1;
        }

        return $params;
    }
}
