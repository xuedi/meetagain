<?php declare(strict_types=1);

namespace App\Controller\Admin\Logs;

use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Service\System\SystemLogService;
use App\ValueObject\LogEntry;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/logs/system')]
final class SystemLogController extends AbstractLogsController implements AdminTabsInterface
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

    /** @var array<string, list<string>|null> Maps a level filter value to the Monolog levels it includes. `null` means no level filter. */
    private const array LEVEL_FILTERS = [
        'all' => null,
        'problems' => ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY'],
        'warning' => ['WARNING'],
        'error' => ['ERROR'],
        'critical' => ['CRITICAL', 'ALERT', 'EMERGENCY'],
    ];

    public function __construct(
        TranslatorInterface $translator,
        private readonly SystemLogService $systemLogService,
    ) {
        parent::__construct($translator, 'system');
    }

    #[Route('', name: 'app_admin_system_log')]
    public function list(Request $request): Response
    {
        $range = $request->query->getString('range', self::DEFAULT_RANGE);
        if (!array_key_exists($range, self::RANGE_OFFSETS)) {
            $range = self::DEFAULT_RANGE;
        }
        $level = $request->query->getString('level', 'all');
        if (!array_key_exists($level, self::LEVEL_FILTERS)) {
            $level = 'all';
        }

        $rangeOffset = self::RANGE_OFFSETS[$range];
        $since = $rangeOffset !== null ? new DateTimeImmutable($rangeOffset) : null;
        $levels = self::LEVEL_FILTERS[$level];

        $allEntries = $this->systemLogService->getAllEntries();
        $totalCount = count($allEntries);
        $filteredEntries = $this->systemLogService->filterEntries($allEntries, $since, $levels);

        $adminTop = new AdminTop(
            info: $this->buildInfo($totalCount, $allEntries, $since),
            actions: [
                $this->buildLevelDropdown($level, $range, $allEntries, $since),
                $this->buildRangeDropdown($range, $level, $allEntries, $levels),
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_logs.action_cleanup'),
                    target: $this->generateUrl('app_admin_system_log_cleanup'),
                    icon: 'broom',
                ),
            ],
        );

        return $this->render('admin/logs/logs_system_list.html.twig', [
            'active' => 'logs',
            'activeLog' => 'logs',
            'logs' => $filteredEntries,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/cleanup', name: 'app_admin_system_log_cleanup')]
    public function cleanup(): Response
    {
        $deleted = $this->systemLogService->deleteOlderThan(new DateTimeImmutable('-1 month'));
        $this->addFlash('success', $this->translator->trans(
            'admin_logs.flash_cleanup_done',
            ['%count%' => $deleted],
        ));

        return $this->redirectToRoute('app_admin_system_log');
    }

    /**
     * @param list<LogEntry> $allEntries
     * @return list<AdminTopInfoHtml>
     */
    private function buildInfo(int $totalCount, array $allEntries, ?DateTimeImmutable $since): array
    {
        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $totalCount,
                $this->translator->trans('admin_logs.summary_total_entries'),
            )),
        ];

        $rangeEntries = $since !== null
            ? $this->systemLogService->filterEntries($allEntries, $since, null)
            : $allEntries;
        $errorCount = $this->systemLogService->countByLevels($rangeEntries, ['ERROR', 'CRITICAL', 'ALERT', 'EMERGENCY']);
        $warningCount = $this->systemLogService->countByLevels($rangeEntries, ['WARNING']);

        if ($since !== null) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($rangeEntries),
                $this->translator->trans('admin_logs.summary_in_range'),
            ));
        }

        if ($errorCount > 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-danger is-medium">%d&nbsp;%s</span>',
                $errorCount,
                $this->translator->trans('admin_logs.summary_with_errors'),
            ));
        } elseif ($warningCount > 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-warning is-medium">%d&nbsp;%s</span>',
                $warningCount,
                $this->translator->trans('admin_logs.summary_with_warnings'),
            ));
        } else {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-success is-medium">%s</span>',
                $this->translator->trans('admin_logs.summary_all_ok'),
            ));
        }

        return $info;
    }

    /**
     * @param list<LogEntry> $allEntries
     */
    private function buildLevelDropdown(string $current, string $range, array $allEntries, ?DateTimeImmutable $since): AdminTopActionDropdown
    {
        $rangeEntries = $this->systemLogService->filterEntries($allEntries, $since, null);
        $options = [];
        foreach (self::LEVEL_FILTERS as $key => $levels) {
            $params = [];
            if ($key !== 'all') {
                $params['level'] = $key;
            }
            if ($range !== self::DEFAULT_RANGE) {
                $params['range'] = $range;
            }
            $count = match ($key) {
                'warning', 'error', 'critical' => $this->systemLogService->countByLevels($rangeEntries, $levels),
                default => null,
            };
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_logs.level_filter_' . $key),
                target: $this->generateUrl('app_admin_system_log', $params),
                isActive: $key === $current,
                count: $count,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_logs.level_filter_label'),
                $this->translator->trans('admin_logs.level_filter_' . $current),
            ),
            options: $options,
            icon: 'filter',
        );
    }

    /**
     * @param list<LogEntry> $allEntries
     * @param list<string>|null $levels
     */
    private function buildRangeDropdown(string $current, string $level, array $allEntries, ?array $levels): AdminTopActionDropdown
    {
        $options = [];
        foreach (self::RANGE_OFFSETS as $key => $offset) {
            $params = [];
            if ($key !== self::DEFAULT_RANGE) {
                $params['range'] = $key;
            }
            if ($level !== 'all') {
                $params['level'] = $level;
            }
            $optionSince = $offset !== null ? new DateTimeImmutable($offset) : null;
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_logs.range_' . $key),
                target: $this->generateUrl('app_admin_system_log', $params),
                isActive: $key === $current,
                count: count($this->systemLogService->filterEntries($allEntries, $optionSince, $levels)),
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
