<?php declare(strict_types=1);

namespace App\Controller\Admin\Seo;

use App\Admin\Dashboard\CounterTile;
use App\Admin\Dashboard\ListTile;
use App\Admin\Dashboard\TileListItem;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Filter\Admin\Event\AdminEventListFilterService;
use App\Service\Seo\SeoDashboardService;
use App\ValueObject\SeoDashboardSummary;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/seo')]
final class DashboardController extends AbstractSeoController implements AdminNavigationInterface, AdminTabsInterface
{
    public function __construct(
        TranslatorInterface $translator,
        private readonly SeoDashboardService $dashboardService,
        private readonly AdminEventListFilterService $eventListFilterService,
    ) {
        parent::__construct($translator, 'dashboard');
    }

    #[Route('', name: 'app_admin_seo', methods: ['GET'])]
    public function dashboard(): Response
    {
        $summary = $this->dashboardService->getSummary($this->eventListFilterService->getEventIdFilter()->getEventIds());

        return $this->render('admin/seo/dashboard/index.html.twig', [
            'active' => 'seo',
            'tiles' => $this->buildTiles($summary),
            'details' => $this->buildDetails($summary),
            'adminTop' => new AdminTop(info: [new AdminTopInfoText($this->translator->trans('admin_seo_dashboard.intro'))]),
            'adminTabs' => $this->getTabs(),
        ]);
    }

    /**
     * @return list<CounterTile>
     */
    private function buildTiles(SeoDashboardSummary $summary): array
    {
        return [
            new CounterTile(
                title: 'admin_seo_dashboard.tile_meta',
                value: sprintf('%d / %d', $summary->descriptionsConfigured, $summary->descriptionsTotal),
                sublabel: 'admin_seo_dashboard.tile_meta_sub',
                icon: 'tags',
                link: $this->generateUrl('app_admin_seo_meta'),
            ),
            new CounterTile(
                title: 'admin_seo_dashboard.tile_sitemap',
                value: $summary->sitemapUrls,
                sublabel: 'admin_seo_dashboard.tile_sitemap_sub',
                icon: 'sitemap',
                link: $this->generateUrl('app_admin_seo_sitemap'),
            ),
            new CounterTile(
                title: 'admin_seo_dashboard.tile_indexnow',
                value: $summary->indexNowLastSubmittedAt?->format('Y-m-d') ?? '-',
                sublabel: $summary->indexNowLastSubmittedAt === null
                    ? 'admin_seo_dashboard.tile_indexnow_never'
                    : 'admin_seo_dashboard.tile_indexnow_sub',
                icon: 'paper-plane',
                link: $this->generateUrl('app_admin_seo_indexnow'),
            ),
            new CounterTile(
                title: 'admin_seo_dashboard.tile_canonical',
                value: $summary->canonicalBranchedLanes,
                sublabel: 'admin_seo_dashboard.tile_canonical_sub',
                icon: 'link',
                link: $this->generateUrl('app_admin_seo_canonical'),
            ),
        ];
    }

    private function buildDetails(SeoDashboardSummary $summary): ListTile
    {
        $items = [];

        if ($summary->sitemapWarnings > 0) {
            $items[] = new TileListItem(
                label: $this->translator->trans('admin_seo_dashboard.detail_sitemap_warnings', ['%count%' => $summary->sitemapWarnings]),
                link: $this->generateUrl('app_admin_seo_sitemap', ['warnings' => 1]),
            );
        }

        $items[] = new TileListItem(
            label: $this->translator->trans('admin_seo_dashboard.detail_canonical_lanes', ['%count%' => $summary->canonicalLanes]),
            sublabel: $this->translator->trans('admin_seo_dashboard.detail_canonical_markers', ['%count%' => $summary->canonicalMarkers]),
            link: $this->generateUrl('app_admin_seo_canonical'),
        );

        return new ListTile(
            title: 'admin_seo_dashboard.details_title',
            items: $items,
            emptyMessage: 'admin_seo_dashboard.details_empty',
        );
    }
}
