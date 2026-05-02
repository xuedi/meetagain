<?php declare(strict_types=1);

namespace App\Controller\Admin;

use App\Admin\Tabs\AdminTabs;
use App\Admin\Tabs\AdminTabsFactory;
use App\Admin\Top\AdminTop;
use App\Admin\Top\AdminTopFactory;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Entity\CronLog;
use App\Repository\CronLogRepository;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/logs/cron')]
final class CronLogController extends AbstractAdminController
{
    public function getAdminNavigation(): ?AdminNavigationConfig
    {
        return null;
    }

    public function __construct(
        private readonly CronLogRepository $cronLogRepository,
        private readonly AdminTopFactory $adminTopFactory,
        private readonly AdminTabsFactory $adminTabsFactory,
        private readonly TranslatorInterface $translator,
    ) {}

    #[Route('', name: 'app_admin_cron_log')]
    public function list(Request $request): Response
    {
        $problemsOnly = $request->query->getBoolean('problemsOnly');
        $showAll = $request->query->getBoolean('showAll');
        $since = $showAll ? null : new DateTimeImmutable('-24 hours');
        $logs = $problemsOnly
            ? $this->cronLogRepository->findRecentProblems(5000, $since)
            : $this->cronLogRepository->findRecent(5000, $since);
        $totalCount = $this->cronLogRepository->countAll();
        $problemCount = $this->cronLogRepository->countProblems();

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $totalCount,
                $this->translator->trans('admin_logs.summary_total'),
            )),
        ];
        if (!$showAll) {
            $info[] = new AdminTopInfoText($this->translator->trans('admin_logs.filter_last_24h_info'));
        }
        $info[] = $problemCount > 0
            ? new AdminTopInfoHtml(sprintf(
                '<span class="tag is-danger is-medium">%d&nbsp;%s</span>',
                $problemCount,
                $this->translator->trans('admin_logs.summary_problems'),
            ))
            : new AdminTopInfoHtml(sprintf(
                '<span class="tag is-success is-medium">%s</span>',
                $this->translator->trans('admin_logs.summary_all_ok'),
            ));

        $problemsToggle = $problemsOnly
            ? $this->adminTopFactory->actionButton(
                labelKey: 'admin_logs.filter_show_all',
                route: 'app_admin_cron_log',
                routeParams: $showAll ? ['showAll' => 1] : [],
                icon: 'list',
            )
            : $this->adminTopFactory->actionButton(
                labelKey: 'admin_logs.filter_problems_only',
                route: 'app_admin_cron_log',
                routeParams: $showAll ? ['problemsOnly' => 1, 'showAll' => 1] : ['problemsOnly' => 1],
                icon: 'filter',
            );

        $timeToggle = $showAll
            ? $this->adminTopFactory->actionButton(
                labelKey: 'admin_logs.filter_last_24h',
                route: 'app_admin_cron_log',
                routeParams: $problemsOnly ? ['problemsOnly' => 1] : [],
                icon: 'clock',
            )
            : $this->adminTopFactory->actionButton(
                labelKey: 'admin_logs.filter_show_all_time',
                route: 'app_admin_cron_log',
                routeParams: $problemsOnly ? ['problemsOnly' => 1, 'showAll' => 1] : ['showAll' => 1],
                icon: 'list',
            );

        $adminTop = new AdminTop(info: $info, actions: [$problemsToggle, $timeToggle]);

        return $this->render('admin/logs/logs_cron_list.html.twig', [
            'active' => 'logs',
            'logs' => $logs,
            'adminTop' => $adminTop,
            'adminTabs' => $this->buildLogsTabs(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_cron_log_show')]
    public function show(CronLog $cronLog): Response
    {
        $statusValue = $cronLog->getStatus()->value;
        $statusTag = match ($statusValue) {
            'ok' => sprintf(
                '<span class="tag is-success is-medium">%s</span>',
                $this->translator->trans('admin_logs.status_ok'),
            ),
            'warning' => sprintf(
                '<span class="tag is-warning is-medium">%s</span>',
                $this->translator->trans('admin_logs.status_warning'),
            ),
            'error' => sprintf(
                '<span class="tag is-danger is-medium">%s</span>',
                $this->translator->trans('admin_logs.status_error'),
            ),
            default => sprintf(
                '<span class="tag is-danger is-dark is-medium">%s</span>',
                $this->translator->trans('admin_logs.status_exception'),
            ),
        };

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%s</strong>',
                    $cronLog->getRunAt()->format('Y-m-d H:i:s'),
                )),
                new AdminTopInfoHtml($statusTag),
                new AdminTopInfoHtml(sprintf(
                    '<span class="has-text-grey">%d %s</span>',
                    $cronLog->getDurationMs(),
                    $this->translator->trans('admin_logs.duration_ms_total'),
                )),
            ],
            actions: [
                $this->adminTopFactory->actionButton(
                    labelKey: 'admin_logs.back',
                    route: 'app_admin_cron_log',
                    icon: 'arrow-left',
                ),
            ],
        );

        return $this->render('admin/logs/logs_cron_show.html.twig', [
            'active' => 'logs',
            'log' => $cronLog,
            'adminTop' => $adminTop,
            'adminTabs' => $this->buildLogsTabs(),
        ]);
    }

    private function buildLogsTabs(): AdminTabs
    {
        return new AdminTabs([
            $this->adminTabsFactory->tab('admin_logs.tab_activity', 'app_admin_activity_log', icon: 'list'),
            $this->adminTabsFactory->tab('admin_logs.tab_system', 'app_admin_system_log', icon: 'file-alt'),
            $this->adminTabsFactory->tab('admin_logs.tab_404', 'app_admin_not_found_log', icon: 'exclamation-triangle'),
            $this->adminTabsFactory->tab('admin_logs.tab_cron', 'app_admin_cron_log', icon: 'clock', isActive: true),
        ]);
    }
}
