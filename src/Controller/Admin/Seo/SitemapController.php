<?php declare(strict_types=1);

namespace App\Controller\Admin\Seo;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Service\Config\LanguageService;
use App\Service\Seo\SitemapOverviewService;
use App\ValueObject\SitemapRow;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/seo')]
final class SitemapController extends AbstractSeoController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly SitemapOverviewService $overviewService,
        private readonly LanguageService $languageService,
    ) {
        parent::__construct($translator, 'sitemap');
    }

    #[Route('', name: 'app_admin_seo_sitemap', methods: ['GET'])]
    public function sitemap(Request $request): Response
    {
        $locales = $this->languageService->getFilteredEnabledCodes();
        $allRows = $this->overviewService->getRows();
        $sections = $this->overviewService->collectSections($allRows);

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
            $allRows,
            static fn(SitemapRow $row): bool => (
                ($localeFilter === '' || $row->locale === $localeFilter)
                && ($sectionFilter === '' || $row->section === $sectionFilter)
                && (!$warningsOnly || $row->hasWarnings())
            ),
        );

        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', count($allRows), $this->translator->trans('admin_seo_sitemap.summary_urls'))),
        ];
        if ($localeFilter !== '' || $sectionFilter !== '' || $warningsOnly) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($rows),
                $this->translator->trans('admin_seo_sitemap.summary_shown'),
            ));
        }
        $warningsTotal = $this->overviewService->countWarnings($allRows);
        if ($warningsTotal > 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-warning is-medium"><strong>%d</strong>&nbsp;%s</span>',
                $warningsTotal,
                $this->translator->trans('admin_seo_sitemap.summary_with_warnings'),
            ));
        }

        $adminTop = new AdminTop(info: $info, actions: [
            $this->buildWarningsToggle($warningsOnly, $localeFilter, $sectionFilter),
            $this->buildSectionDropdown($sectionFilter, $localeFilter, $warningsOnly, $allRows, $sections),
            $this->buildLocaleDropdown($localeFilter, $sectionFilter, $warningsOnly, $allRows, $locales),
        ]);

        return $this->render('admin/seo/sitemap/index.html.twig', [
            'active' => 'seo',
            'urls' => array_values($rows),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    /**
     * @param list<SitemapRow> $allRows
     * @param list<string> $sections
     */
    private function buildSectionDropdown(string $current, string $locale, bool $warnings, array $allRows, array $sections): AdminTopActionDropdown
    {
        $base = $this->buildBaseParams($locale, '', $warnings);

        $options = [
            new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_seo_sitemap.section_filter_all'),
                target: $this->generateUrl('app_admin_seo_sitemap', $base),
                isActive: $current === '',
            ),
        ];
        foreach ($sections as $section) {
            $count = 0;
            foreach ($allRows as $row) {
                if (!($row->section === $section && ($locale === '' || $row->locale === $locale))) {
                    continue;
                }

                ++$count;
            }
            $options[] = new AdminTopActionDropdownOption(
                label: $this->labelForSection($section),
                target: $this->generateUrl('app_admin_seo_sitemap', ['section' => $section] + $base),
                isActive: $current === $section,
                count: $count,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_seo_sitemap.section_filter_label'),
                $current === '' ? $this->translator->trans('admin_seo_sitemap.section_filter_all') : $this->labelForSection($current),
            ),
            options: $options,
            icon: 'layer-group',
        );
    }

    private function labelForSection(string $section): string
    {
        $key = 'admin_seo_sitemap.section_' . $section;
        $translated = $this->translator->trans($key);
        if ($translated !== $key) {
            return $translated;
        }

        return ucfirst($section);
    }

    /**
     * @param list<SitemapRow> $allRows
     * @param list<string> $locales
     */
    private function buildLocaleDropdown(string $current, string $section, bool $warnings, array $allRows, array $locales): AdminTopActionDropdown
    {
        $base = $this->buildBaseParams('', $section, $warnings);

        $options = [
            new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_seo_sitemap.locale_filter_all'),
                target: $this->generateUrl('app_admin_seo_sitemap', $base),
                isActive: $current === '',
            ),
        ];
        foreach ($locales as $locale) {
            $count = 0;
            foreach ($allRows as $row) {
                if (!($row->locale === $locale && ($section === '' || $row->section === $section))) {
                    continue;
                }

                ++$count;
            }
            $options[] = new AdminTopActionDropdownOption(
                label: strtoupper($locale),
                target: $this->generateUrl('app_admin_seo_sitemap', ['locale' => $locale] + $base),
                isActive: $current === $locale,
                count: $count,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_seo_sitemap.locale_filter_label'),
                $current === '' ? $this->translator->trans('admin_seo_sitemap.locale_filter_all') : strtoupper($current),
            ),
            options: $options,
            icon: 'language',
        );
    }

    private function buildWarningsToggle(bool $current, string $locale, string $section): AdminTopActionButton
    {
        $params = $this->buildBaseParams($locale, $section, !$current);

        return new AdminTopActionButton(
            label: $this->translator->trans($current ? 'admin_seo_sitemap.button_hide_warnings' : 'admin_seo_sitemap.button_show_warnings'),
            target: $this->generateUrl('app_admin_seo_sitemap', $params),
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
