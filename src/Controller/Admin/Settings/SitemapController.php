<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Service\Config\LanguageService;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system')]
final class SitemapController extends AbstractSettingsController implements AdminNavigationInterface, AdminTabsInterface
{
    private const array SECTIONS = ['static', 'cms', 'events'];

    public function __construct(
        TranslatorInterface $translator,
        private readonly EventRepository $eventRepository,
        private readonly CmsRepository $cmsRepository,
        private readonly LanguageService $languageService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
        parent::__construct($translator, 'sitemap');
    }

    #[Route('/sitemap', name: 'app_admin_system_sitemap')]
    public function sitemap(Request $request): Response
    {
        $locales = $this->languageService->getFilteredEnabledCodes();
        $allUrls = $this->collectUrls($locales);

        $localeFilter = $request->query->getString('locale');
        if ($localeFilter !== '' && !in_array($localeFilter, $locales, true)) {
            $localeFilter = '';
        }

        $sectionFilter = $request->query->getString('section');
        if ($sectionFilter !== '' && !in_array($sectionFilter, self::SECTIONS, true)) {
            $sectionFilter = '';
        }

        $warningsOnly = $request->query->getBoolean('warnings');

        $rows = array_filter(
            $allUrls,
            static fn(array $row): bool => ($localeFilter === '' || $row['locale'] === $localeFilter)
                && ($sectionFilter === '' || $row['section'] === $sectionFilter)
                && (!$warningsOnly || count($row['warnings']) > 0),
        );

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($allUrls),
                $this->translator->trans('admin_system_sitemap.summary_urls'),
            )),
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

        $adminTop = new AdminTop(
            info: $info,
            actions: [
                $this->buildWarningsToggle($warningsOnly, $localeFilter, $sectionFilter),
                $this->buildSectionDropdown($sectionFilter, $localeFilter, $warningsOnly, $allUrls),
                $this->buildLocaleDropdown($localeFilter, $sectionFilter, $warningsOnly, $allUrls, $locales),
            ],
        );

        return $this->render('admin/system/sitemap/index.html.twig', [
            'active' => 'system',
            'urls' => array_values($rows),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    /**
     * @param list<string> $locales
     *
     * @return list<array{section: string, label: string, url: string, locale: string, lastmod: string, warnings: list<string>}>
     */
    private function collectUrls(array $locales): array
    {
        $urls = [];

        $staticRoutes = [
            ['route' => 'app_default', 'params' => [], 'label' => 'Home'],
            ['route' => 'app_event', 'params' => [], 'label' => 'Events list'],
            ['route' => 'app_member', 'params' => ['page' => 1], 'label' => 'Members list'],
        ];

        foreach ($locales as $locale) {
            foreach ($staticRoutes as $route) {
                $urls[] = [
                    'section' => 'static',
                    'label' => $route['label'],
                    'url' => $this->urlGenerator->generate(
                        $route['route'],
                        ['_locale' => $locale, ...$route['params']],
                        UrlGeneratorInterface::ABSOLUTE_URL,
                    ),
                    'locale' => $locale,
                    'lastmod' => date('Y-m-d'),
                    'warnings' => [],
                ];
            }
        }

        foreach ($this->cmsRepository->findPublished() as $page) {
            $slug = $page->getSlug();
            if ($slug === null) {
                continue;
            }
            foreach ($locales as $locale) {
                $title = $page->getPageTitle($locale);
                $warnings = [];
                if ($title === null || $title === '') {
                    $warnings[] = $this->translator->trans('admin_system_sitemap.warning_missing_title');
                }
                $urls[] = [
                    'section' => 'cms',
                    'label' => $title !== null && $title !== '' ? $title : $slug,
                    'url' => $this->urlGenerator->generate(
                        'app_catch_all',
                        ['_locale' => $locale, 'page' => $slug],
                        UrlGeneratorInterface::ABSOLUTE_URL,
                    ),
                    'locale' => $locale,
                    'lastmod' => $page->getCreatedAt()?->format('Y-m-d') ?? '-',
                    'warnings' => $warnings,
                ];
            }
        }

        foreach ($this->eventRepository->findForSitemap() as $event) {
            $id = $event->getId();
            if ($id === null) {
                continue;
            }
            foreach ($locales as $locale) {
                $title = $event->getTitle($locale);
                $warnings = [];
                if ($title === '') {
                    $warnings[] = $this->translator->trans('admin_system_sitemap.warning_missing_title');
                }
                if ($event->getPreviewImage() === null) {
                    $warnings[] = $this->translator->trans('admin_system_sitemap.warning_no_preview_image');
                }
                $urls[] = [
                    'section' => 'events',
                    'label' => $title !== '' ? $title : "Event #{$id}",
                    'url' => $this->urlGenerator->generate(
                        'app_event_details',
                        ['_locale' => $locale, 'id' => $id],
                        UrlGeneratorInterface::ABSOLUTE_URL,
                    ),
                    'locale' => $locale,
                    'lastmod' => $event->getStart()->format('Y-m-d'),
                    'warnings' => $warnings,
                ];
            }
        }

        return $urls;
    }

    private function countWarnings(array $urls): int
    {
        $count = 0;
        foreach ($urls as $url) {
            if (count($url['warnings']) > 0) {
                ++$count;
            }
        }

        return $count;
    }

    private function buildSectionDropdown(string $current, string $locale, bool $warnings, array $allUrls): AdminTopActionDropdown
    {
        $base = $this->buildBaseParams($locale, '', $warnings);

        $options = [
            new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_system_sitemap.section_filter_all'),
                target: $this->generateUrl('app_admin_system_sitemap', $base),
                isActive: $current === '',
            ),
        ];
        foreach (self::SECTIONS as $section) {
            $count = 0;
            foreach ($allUrls as $row) {
                if ($row['section'] === $section && ($locale === '' || $row['locale'] === $locale)) {
                    ++$count;
                }
            }
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_system_sitemap.section_' . $section),
                target: $this->generateUrl('app_admin_system_sitemap', ['section' => $section] + $base),
                isActive: $current === $section,
                count: $count,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_system_sitemap.section_filter_label'),
                $current === ''
                    ? $this->translator->trans('admin_system_sitemap.section_filter_all')
                    : $this->translator->trans('admin_system_sitemap.section_' . $current),
            ),
            options: $options,
            icon: 'layer-group',
        );
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
                if ($row['locale'] === $locale && ($section === '' || $row['section'] === $section)) {
                    ++$count;
                }
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
                $current === ''
                    ? $this->translator->trans('admin_system_sitemap.locale_filter_all')
                    : strtoupper($current),
            ),
            options: $options,
            icon: 'language',
        );
    }

    private function buildWarningsToggle(bool $current, string $locale, string $section): AdminTopActionButton
    {
        $params = $this->buildBaseParams($locale, $section, !$current);

        return new AdminTopActionButton(
            label: $this->translator->trans(
                $current ? 'admin_system_sitemap.button_hide_warnings' : 'admin_system_sitemap.button_show_warnings',
            ),
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
