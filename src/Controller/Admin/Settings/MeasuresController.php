<?php declare(strict_types=1);

namespace App\Controller\Admin\Settings;

use App\Admin\Dashboard\ListTile;
use App\Admin\Dashboard\MultiSeriesChartTile;
use App\Admin\Dashboard\TileDataset;
use App\Admin\Dashboard\TileListItem;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\Actions\AdminTopActionForm;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Enum\SecurityMeasure;
use App\Form\SecurityMeasuresType;
use App\Repository\IncidentRepository;
use App\Repository\RateLimitLogRepository;
use App\Repository\SecurityMeasureLogRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\Security\BlockedSessionStore;
use App\Service\Security\MeasureSettings;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/system/security')]
final class MeasuresController extends AbstractSettingsController implements AdminNavigationInterface, AdminTabsInterface
{
    private const string DEFAULT_RANGE = '1w';
    private const int CHART_DAYS = 14;

    /** @var array<string, string|null> */
    private const array RANGE_OFFSETS = [
        '1w' => '-1 week',
        '1m' => '-1 month',
        'all' => null,
    ];

    public function __construct(
        TranslatorInterface $translator,
        private readonly MeasureSettings $measureSettings,
        private readonly SecurityMeasureLogRepository $measureLogRepo,
        private readonly IncidentRepository $incidentRepo,
        private readonly RateLimitLogRepository $rateLimitLogRepo,
        private readonly BlockedSessionStore $blockedSessionStore,
        private readonly Connection $connection,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($translator, 'config');
    }

    #[Route('', name: 'app_admin_system_security', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SETTINGS_READ);

        $range = $request->query->getString('range', self::DEFAULT_RANGE);
        if (!array_key_exists($range, self::RANGE_OFFSETS)) {
            $range = self::DEFAULT_RANGE;
        }
        $sinceDay = $this->resolveSinceDay($range);

        $form = $this->createForm(SecurityMeasuresType::class, [
            'powDifficulty' => $this->measureSettings->proofOfWorkDifficulty(),
            'logRetentionDays' => $this->measureSettings->logRetentionDays(),
        ]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SETTINGS_UPDATE);
            $this->measureSettings->save($form->getData());
            $this->addFlash('success', $this->translator->trans('admin_system_security.flash_saved'));

            return $this->redirectToRoute('app_admin_system_security', $this->rangeParams($range));
        }

        $summary = $this->measureLogRepo->summaryByMeasure($sinceDay);
        $totalBlocks = $this->measureLogRepo->countBlocks();
        $rangeBlocks = $sinceDay === null ? $totalBlocks : $this->measureLogRepo->countBlocks($sinceDay);

        $measures = [];
        foreach (SecurityMeasure::cases() as $measure) {
            $measures[] = [
                'measure' => $measure,
                'enabled' => $this->measureSettings->isEnabled($measure),
                'checked' => $summary[$measure->value]['checked'],
                'blocked' => $summary[$measure->value]['blocked'],
                'lastBlock' => $summary[$measure->value]['lastBlock'],
            ];
        }

        return $this->render('admin/system/security/index.html.twig', [
            'active' => 'system',
            'adminTop' => $this->buildTop($range, $totalBlocks, $rangeBlocks),
            'adminTabs' => $this->getTabs(),
            'form' => $form,
            'measures' => $measures,
            'chartTile' => $this->buildChartTile(),
            'elsewhereTile' => $this->buildElsewhereTile($sinceDay, $totalBlocks),
            'recentBlocks' => $this->measureLogRepo->recentBlocks(100, $sinceDay),
            'range' => $range,
        ]);
    }

    #[Route('/toggle/{measure}', name: 'app_admin_system_security_toggle', methods: ['POST'])]
    public function toggle(Request $request, string $measure): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SETTINGS_UPDATE);

        $case = SecurityMeasure::tryFrom($measure);
        if ($case === null) {
            throw $this->createNotFoundException();
        }

        if (!$this->isCsrfTokenValid('admin_system_security_toggle' . $measure, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $enabled = !$this->measureSettings->isEnabled($case);
        $this->measureSettings->setEnabled($case, $enabled);

        if ($request->isXmlHttpRequest()) {
            return new JsonResponse(['newStatus' => $enabled]);
        }

        return $this->redirectToRoute('app_admin_system_security');
    }

    #[Route('/clear', name: 'app_admin_system_security_clear', methods: ['POST'])]
    public function clear(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SETTINGS_UPDATE);

        if (!$this->isCsrfTokenValid('admin_system_security_clear', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->connection->executeStatement('DELETE FROM logs_security_measure');

        return $this->redirectToRoute('app_admin_system_security');
    }

    private function buildTop(string $range, int $totalBlocks, int $rangeBlocks): AdminTop
    {
        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', $totalBlocks, $this->translator->trans('admin_system_security.summary_total_blocks'))),
        ];

        if ($rangeBlocks === 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-success is-medium">%s</span>',
                $this->translator->trans('admin_system_security.summary_no_blocks'),
            ));
        } else {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-warning is-medium">%d %s</span>',
                $rangeBlocks,
                $this->translator->trans('admin_system_security.summary_in_range'),
            ));
        }

        $actions = [];
        if ($totalBlocks > 0) {
            $actions[] = new AdminTopActionForm(
                label: $this->translator->trans('global.button_clear'),
                target: $this->generateUrl('app_admin_system_security_clear'),
                csrfTokenId: 'admin_system_security_clear',
                icon: 'trash',
            );
        }
        $actions[] = $this->buildRangeDropdown($range);
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('admin_system_security.button_measure_log'),
            target: $this->generateUrl('app_admin_security_measures'),
            icon: 'list',
        );
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('global.button_back'),
            target: $this->generateUrl('app_admin_system_config'),
            icon: 'arrow-left',
        );

        return new AdminTop(info: $info, actions: $actions);
    }

    private function buildRangeDropdown(string $current): AdminTopActionDropdown
    {
        $options = [];
        foreach (array_keys(self::RANGE_OFFSETS) as $key) {
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_logs.range_' . $key),
                target: $this->generateUrl('app_admin_system_security', $this->rangeParams($key)),
                isActive: $key === $current,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf('%s %s', $this->translator->trans('admin_logs.range_label'), $this->translator->trans('admin_logs.range_' . $current)),
            options: $options,
            icon: 'clock',
        );
    }

    private function buildChartTile(): MultiSeriesChartTile
    {
        $firstDay = $this->clock
            ->now()
            ->setTime(0, 0)
            ->modify(sprintf('-%d days', self::CHART_DAYS - 1));
        $series = $this->measureLogRepo->dailyBlockSeries($firstDay);

        $labels = [];
        for ($offset = 0; $offset < self::CHART_DAYS; $offset++) {
            $labels[] = $firstDay->modify(sprintf('+%d days', $offset))->format('Y-m-d');
        }

        $datasets = [];
        foreach (SecurityMeasure::cases() as $measure) {
            $data = [];
            foreach ($labels as $label) {
                $data[] = $series[$measure->value][$label] ?? 0;
            }

            $datasets[] = new TileDataset(label: $this->translator->trans($measure->labelKey()), data: $data, borderColor: $measure->color());
        }

        return new MultiSeriesChartTile(title: 'admin_system_security.chart_title', canvasId: 'securityMeasureChart', labels: $labels, datasets: $datasets);
    }

    private function buildElsewhereTile(?DateTimeImmutable $sinceDay, int $totalBlocks): ListTile
    {
        $incidents = $sinceDay === null ? $this->incidentRepo->countAll() : $this->incidentRepo->countSince($sinceDay);
        $rateLimits = $sinceDay === null ? $this->rateLimitLogRepo->countAll() : $this->rateLimitLogRepo->countSince($sinceDay);
        $blocked = count($this->blockedSessionStore->listBlockedSessions()) + count($this->blockedSessionStore->listBlockedIps());

        return new ListTile(title: 'admin_system_security.heading_elsewhere', items: [
            new TileListItem(
                label: sprintf('%d %s', $incidents, $this->translator->trans('admin_system_security.elsewhere_incidents')),
                link: $this->generateUrl('app_admin_security_incidents'),
            ),
            new TileListItem(
                label: sprintf('%d %s', $blocked, $this->translator->trans('admin_system_security.elsewhere_blocked')),
                link: $this->generateUrl('app_admin_security_blocked'),
            ),
            new TileListItem(
                label: sprintf('%d %s', $rateLimits, $this->translator->trans('admin_system_security.elsewhere_rate_limits')),
                link: $this->generateUrl('app_admin_security_rate_limiting'),
            ),
            new TileListItem(
                label: sprintf('%d %s', $totalBlocks, $this->translator->trans('admin_system_security.elsewhere_measure_blocks')),
                link: $this->generateUrl('app_admin_security_measures'),
            ),
        ]);
    }

    private function resolveSinceDay(string $range): ?DateTimeImmutable
    {
        $offset = self::RANGE_OFFSETS[$range];

        return $offset === null ? null : $this->clock->now()->setTime(0, 0)->modify($offset);
    }

    /**
     * @return array<string, string>
     */
    private function rangeParams(string $range): array
    {
        return $range === self::DEFAULT_RANGE ? [] : ['range' => $range];
    }
}
