<?php declare(strict_types=1);

namespace App\Controller\Admin\Logs;

use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\CronLog;
use App\Repository\CronLogRepository;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/logs/cron')]
final class CronLogController extends AbstractLogsController implements AdminTabsInterface
{
    private const string DEFAULT_RANGE = '1h';

    /** @var array<string, string|null> */
    private const array RANGE_OFFSETS = [
        '1h' => '-1 hour',
        '6h' => '-6 hours',
        '24h' => '-24 hours',
        '1w' => '-1 week',
        'all' => null,
    ];

    /** @var array<string, list<string>|null> */
    private const array STATUS_FILTERS = [
        'all' => null,
        'problems' => ['warning', 'error', 'exception'],
        'warning' => ['warning'],
        'error' => ['error'],
        'exception' => ['exception'],
    ];

    public function __construct(
        TranslatorInterface $translator,
        private readonly CronLogRepository $cronLogRepository,
    ) {
        parent::__construct($translator, 'cron');
    }

    #[Route('', name: 'app_admin_cron_log')]
    public function list(Request $request): Response
    {
        $range = $request->query->getString('range', self::DEFAULT_RANGE);
        if (!array_key_exists($range, self::RANGE_OFFSETS)) {
            $range = self::DEFAULT_RANGE;
        }
        $status = $request->query->getString('status', 'all');
        if (!array_key_exists($status, self::STATUS_FILTERS)) {
            $status = 'all';
        }

        $rangeOffset = self::RANGE_OFFSETS[$range];
        $since = $rangeOffset !== null ? new DateTimeImmutable($rangeOffset) : null;
        $statuses = self::STATUS_FILTERS[$status];

        $logs = $this->cronLogRepository->findRecent(5000, $since, $statuses);
        $totalCount = $this->cronLogRepository->countAll();
        $problemCount = $this->cronLogRepository->countProblems();

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $totalCount,
                $this->translator->trans('admin_logs.summary_total'),
            )),
            $problemCount > 0
                ? new AdminTopInfoHtml(sprintf(
                    '<span class="tag is-danger is-medium">%d&nbsp;%s</span>',
                    $problemCount,
                    $this->translator->trans('admin_logs.summary_problems'),
                ))
                : new AdminTopInfoHtml(sprintf(
                    '<span class="tag is-success is-medium">%s</span>',
                    $this->translator->trans('admin_logs.summary_all_ok'),
                )),
        ];

        $statusDropdown = $this->buildStatusDropdown($status, $range);
        $rangeDropdown = $this->buildRangeDropdown($range, $status);

        $adminTop = new AdminTop(info: $info, actions: [$statusDropdown, $rangeDropdown]);

        return $this->render('admin/logs/logs_cron_list.html.twig', [
            'active' => 'logs',
            'logs' => $logs,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
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
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_logs.back'),
                    target: $this->generateUrl('app_admin_cron_log'),
                    icon: 'arrow-left',
                ),
            ],
        );

        return $this->render('admin/logs/logs_cron_show.html.twig', [
            'active' => 'logs',
            'log' => $cronLog,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    private function buildStatusDropdown(string $current, string $range): AdminTopActionDropdown
    {
        $options = [];
        foreach (array_keys(self::STATUS_FILTERS) as $key) {
            $params = [];
            if ($key !== 'all') {
                $params['status'] = $key;
            }
            if ($range !== self::DEFAULT_RANGE) {
                $params['range'] = $range;
            }
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_logs.status_filter_' . $key),
                target: $this->generateUrl('app_admin_cron_log', $params),
                isActive: $key === $current,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_logs.status_filter_label'),
                $this->translator->trans('admin_logs.status_filter_' . $current),
            ),
            options: $options,
            icon: 'filter',
        );
    }

    private function buildRangeDropdown(string $current, string $status): AdminTopActionDropdown
    {
        $options = [];
        foreach (array_keys(self::RANGE_OFFSETS) as $key) {
            $params = [];
            if ($key !== self::DEFAULT_RANGE) {
                $params['range'] = $key;
            }
            if ($status !== 'all') {
                $params['status'] = $status;
            }
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_logs.range_' . $key),
                target: $this->generateUrl('app_admin_cron_log', $params),
                isActive: $key === $current,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_logs.range_label'),
                $this->translator->trans('admin_logs.range_' . $current),
            ),
            options: $options,
            icon: 'clock',
        );
    }
}
