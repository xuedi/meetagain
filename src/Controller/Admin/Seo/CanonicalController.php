<?php declare(strict_types=1);

namespace App\Controller\Admin\Seo;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\Actions\AdminTopActionForm;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Entity\EventSeries;
use App\Filter\Admin\Event\AdminEventListFilterService;
use App\Form\EventCanonicalSettingsType;
use App\Repository\EventSeriesRepository;
use App\Service\Config\ConfigService;
use App\Service\Config\LanguageService;
use App\Service\Seo\EventCanonicalOverviewService;
use App\Service\Seo\EventCanonicalRebuildService;
use App\ValueObject\CanonicalLane;
use App\ValueObject\CanonicalRebuildSummary;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/seo')]
final class CanonicalController extends AbstractSeoController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly EventCanonicalOverviewService $overviewService,
        private readonly EventCanonicalRebuildService $rebuildService,
        private readonly EventSeriesRepository $seriesRepository,
        private readonly AdminEventListFilterService $eventListFilterService,
        private readonly LanguageService $languageService,
        private readonly ConfigService $configService,
    ) {
        parent::__construct($translator, 'canonical');
    }

    #[Route('/canonical', name: 'app_admin_seo_canonical', methods: ['GET'])]
    public function canonical(Request $request): Response
    {
        $seriesId = $request->query->getInt('series') ?: null;
        $locale = $request->query->getString('locale') ?: null;
        $onlyBranched = $request->query->getBoolean('branched');

        $lanes = $this->overviewService->getLanes(
            $seriesId,
            $locale,
            $onlyBranched,
            $this->eventListFilterService->getEventIdFilter()->getEventIds(),
        );

        return $this->render('admin/seo/canonical/index.html.twig', [
            'active' => 'seo',
            'lanes' => $lanes,
            'threshold' => $this->configService->getEventCanonicalThreshold(),
            'adminTop' => $this->buildTop($lanes, $seriesId, $locale, $onlyBranched),
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/canonical/config', name: 'app_admin_seo_canonical_config', methods: ['GET', 'POST'])]
    public function config(Request $request): Response
    {
        $form = $this->createForm(EventCanonicalSettingsType::class);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->configService->saveEventCanonicalForm($form->getData());
            $this->addFlash('success', $this->translator->trans('admin_seo_canonical.flash_config_saved'));

            return $this->redirectToRoute('app_admin_seo_canonical_config');
        }

        $adminTop = new AdminTop(
            info: [new AdminTopInfoText($this->translator->trans('admin_seo_canonical.topbar_config'))],
            actions: [$this->backToCanonical()],
        );

        return $this->render('admin/seo/canonical/config.html.twig', [
            'active' => 'seo',
            'form' => $form,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/canonical/rebuild', name: 'app_admin_seo_canonical_rebuild_all', methods: ['POST'])]
    public function rebuildAll(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_seo_canonical_rebuild_all', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->addSummaryFlash($this->rebuildService->rebuildAll());

        return $this->redirectToRoute('app_admin_seo_canonical');
    }

    #[Route('/canonical/{id}/rebuild', name: 'app_admin_seo_canonical_rebuild_series', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function rebuildSeries(int $id, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_seo_canonical_rebuild_series' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $series = $this->seriesRepository->find($id);
        if (!$series instanceof EventSeries) {
            throw new NotFoundHttpException();
        }

        $this->addSummaryFlash($this->rebuildService->rebuildSeries($series));

        return $this->redirectToRoute('app_admin_seo_canonical');
    }

    /**
     * @param array<CanonicalRebuildSummary> $summaries
     */
    private function addSummaryFlash(array $summaries): void
    {
        $roots = 0;
        $detached = 0;
        $removed = 0;
        foreach ($summaries as $summary) {
            $roots += $summary->rootsWritten;
            $detached += $summary->detachedWritten;
            $removed += $summary->markersRemoved;
        }

        $this->addFlash('success', $this->translator->trans('admin_seo_canonical.flash_rebuilt', [
            '%roots%' => $roots,
            '%detached%' => $detached,
            '%removed%' => $removed,
        ]));
    }

    /**
     * @param array<CanonicalLane> $lanes
     */
    private function buildTop(array $lanes, ?int $seriesId, ?string $locale, bool $onlyBranched): AdminTop
    {
        $branched = count(array_filter($lanes, static fn(CanonicalLane $lane) => $lane->isBranched()));

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($lanes),
                $this->escape($this->translator->trans('admin_seo_canonical.summary_lanes')),
            )),
        ];
        if ($branched > 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-warning is-medium"><strong>%d</strong>&nbsp;%s</span>',
                $branched,
                $this->escape($this->translator->trans('admin_seo_canonical.summary_branched')),
            ));
        }

        return new AdminTop(info: $info, actions: [
            new AdminTopActionForm(
                label: $this->translator->trans('admin_seo_canonical.button_rebuild_all'),
                target: $this->generateUrl('app_admin_seo_canonical_rebuild_all'),
                csrfTokenId: 'admin_seo_canonical_rebuild_all',
                icon: 'rotate',
                variant: 'is-warning',
                confirm: $this->translator->trans('admin_seo_canonical.confirm_rebuild_all'),
            ),
            new AdminTopActionButton(
                label: $this->translator->trans('admin_seo_canonical.button_config'),
                target: $this->generateUrl('app_admin_seo_canonical_config'),
                icon: 'cog',
            ),
            $this->seriesDropdown($seriesId, $locale, $onlyBranched),
            $this->localeDropdown($seriesId, $locale, $onlyBranched),
            $this->branchedDropdown($seriesId, $locale, $onlyBranched),
        ]);
    }

    private function backToCanonical(): AdminTopActionButton
    {
        return new AdminTopActionButton(
            label: $this->translator->trans('global.button_back'),
            target: $this->generateUrl('app_admin_seo_canonical'),
            icon: 'arrow-left',
        );
    }

    private function seriesDropdown(?int $seriesId, ?string $locale, bool $onlyBranched): AdminTopActionDropdown
    {
        $options = [new AdminTopActionDropdownOption(
            label: $this->translator->trans('admin_seo_canonical.filter_all'),
            target: $this->filterUrl(null, $locale, $onlyBranched),
            isActive: $seriesId === null,
        )];
        $activeLabel = $this->translator->trans('admin_seo_canonical.filter_all');

        foreach ($this->overviewService->getSeriesOptions() as $id => $name) {
            $options[] = new AdminTopActionDropdownOption(
                label: $name,
                target: $this->filterUrl($id, $locale, $onlyBranched),
                isActive: $seriesId === $id,
            );
            if ($seriesId === $id) {
                $activeLabel = $name;
            }
        }

        return new AdminTopActionDropdown(
            label: sprintf('%s %s', $this->translator->trans('admin_seo_canonical.filter_series_label'), $activeLabel),
            options: $options,
            icon: 'repeat',
        );
    }

    private function localeDropdown(?int $seriesId, ?string $locale, bool $onlyBranched): AdminTopActionDropdown
    {
        $options = [new AdminTopActionDropdownOption(
            label: $this->translator->trans('admin_seo_canonical.filter_all'),
            target: $this->filterUrl($seriesId, null, $onlyBranched),
            isActive: $locale === null,
        )];

        foreach ($this->languageService->getAdminFilteredEnabledCodes() as $code) {
            $options[] = new AdminTopActionDropdownOption(
                label: $code,
                target: $this->filterUrl($seriesId, $code, $onlyBranched),
                isActive: $locale === $code,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_seo_canonical.filter_locale_label'),
                $locale ?? $this->translator->trans('admin_seo_canonical.filter_all'),
            ),
            options: $options,
            icon: 'language',
        );
    }

    private function branchedDropdown(?int $seriesId, ?string $locale, bool $onlyBranched): AdminTopActionDropdown
    {
        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_seo_canonical.filter_branched_label'),
                $this->translator->trans($onlyBranched
                    ? 'admin_seo_canonical.filter_branched_only'
                    : 'admin_seo_canonical.filter_all'),
            ),
            options: [
                new AdminTopActionDropdownOption(
                    label: $this->translator->trans('admin_seo_canonical.filter_all'),
                    target: $this->filterUrl($seriesId, $locale, false),
                    isActive: !$onlyBranched,
                ),
                new AdminTopActionDropdownOption(
                    label: $this->translator->trans('admin_seo_canonical.filter_branched_only'),
                    target: $this->filterUrl($seriesId, $locale, true),
                    isActive: $onlyBranched,
                ),
            ],
            icon: 'code-branch',
        );
    }

    private function filterUrl(?int $seriesId, ?string $locale, bool $onlyBranched): string
    {
        return $this->generateUrl('app_admin_seo_canonical', array_filter([
            'series' => $seriesId,
            'locale' => $locale,
            'branched' => $onlyBranched ? 1 : null,
        ]));
    }

    private function escape(string $value): string
    {
        return htmlspecialchars($value, ENT_QUOTES | ENT_HTML5, 'UTF-8');
    }
}
