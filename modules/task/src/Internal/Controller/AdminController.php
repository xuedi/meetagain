<?php declare(strict_types=1);

namespace Module\Task\Internal\Controller;

use App\Admin\Dashboard\MultiSeriesChartTile;
use App\Admin\Dashboard\TileDataset;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\Actions\AdminTopActionForm;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Controller\Admin\Logs\AbstractLogsController;
use DateTimeImmutable;
use Module\Task\Contract\FinishedRun;
use Module\Task\Contract\Status;
use Module\Task\Contract\TriggerInterface;
use Module\Task\Internal\Action\Registry as ActionRegistry;
use Module\Task\Internal\CadenceTrigger;
use Module\Task\Internal\Queue;
use Module\Task\Internal\Registry;
use Module\Task\Internal\RunLog;
use Module\Task\Internal\RunRetention;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[Route('/admin/logs')]
#[IsGranted('ROLE_ADMIN')]
final class AdminController extends AbstractLogsController implements AdminNavigationInterface, AdminTabsInterface
{
    private const string DEFAULT_RANGE = '1h';
    private const int LIST_LIMIT = 5000;
    private const int HISTORY_LIMIT = 200;
    private const int NEXT_RUNS = 3;

    /** @var array<string, string|null> */
    private const array RANGE_OFFSETS = [
        '1h' => '-1 hour',
        '6h' => '-6 hours',
        '24h' => '-24 hours',
        '1w' => '-1 week',
        'all' => null,
    ];

    /** @var array<string, list<Status>|null> */
    private const array STATUS_FILTERS = [
        'all' => null,
        'problems' => [Status::warning, Status::error, Status::exception],
        'warning' => [Status::warning],
        'error' => [Status::error],
        'exception' => [Status::exception],
    ];

    public function __construct(
        TranslatorInterface $translator,
        private readonly Registry $registry,
        private readonly RunLog $runLog,
        private readonly Queue $queue,
        private readonly TriggerInterface $tasks,
        private readonly ActionRegistry $actions,
    ) {
        parent::__construct($translator, 'tasks');
    }

    #[Route('/tasks', name: 'app_admin_task')]
    public function schedules(): Response
    {
        $now = new DateTimeImmutable();
        $timezone = date_default_timezone_get();
        $latest = $this->runLog->latestPerIdentifier();

        $schedules = [];
        $problems = 0;
        foreach ($this->registry->all() as $identifier => $task) {
            $cadence = $task->getCadence();
            $last = $latest[$identifier] ?? null;
            if ($last !== null && $last->status !== Status::ok) {
                $problems++;
            }
            $schedules[] = [
                'identifier' => $identifier,
                'cadence' => $cadence,
                'next' => new CadenceTrigger($identifier, $cadence, $timezone)->getNextRunDate($now),
                'last' => $last,
            ];
        }

        $adminTop = new AdminTop(info: [
            new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', count($schedules), $this->translator->trans('admin_task.summary_schedules'))),
            $this->problemTag($problems),
        ], actions: [
            new AdminTopActionButton(
                label: $this->translator->trans('admin_task.section_runs'),
                target: $this->generateUrl('app_admin_task_runs'),
                icon: 'list',
            ),
            new AdminTopActionButton(
                label: $this->translator->trans('admin_task.section_queue'),
                target: $this->generateUrl('app_admin_task_queue'),
                icon: 'inbox',
                variant: $this->queueNeedsAttention() ? 'is-warning' : null,
            ),
        ]);

        return $this->render('@Task/admin/schedules.html.twig', [
            'active' => 'logs',
            'schedules' => $schedules,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/cron', name: 'app_admin_cron_log')]
    public function cronLog(): RedirectResponse
    {
        return $this->redirectToRoute('app_admin_task', status: Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/tasks/{identifier}/run', name: 'app_admin_task_run_now', requirements: ['identifier' => '[a-z0-9._\-]+'], methods: ['POST'])]
    public function runNow(string $identifier, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('app_admin_task_run_now' . $identifier, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        if ($this->registry->find($identifier) === null) {
            throw $this->createNotFoundException();
        }

        $this->tasks->runSoon($identifier);
        $this->addFlash('success', $this->translator->trans('admin_task.flash_run_queued', ['%task%' => $identifier]));

        if ($request->query->getString('return') === 'show') {
            return $this->redirectToRoute('app_admin_task_show', ['identifier' => $identifier]);
        }

        return $this->redirectToRoute('app_admin_task');
    }

    #[Route('/tasks/runs', name: 'app_admin_task_runs')]
    public function runs(Request $request): Response
    {
        $range = $request->query->getString('range', self::DEFAULT_RANGE);
        if (!array_key_exists($range, self::RANGE_OFFSETS)) {
            $range = self::DEFAULT_RANGE;
        }
        $status = $request->query->getString('status', 'all');
        if (!array_key_exists($status, self::STATUS_FILTERS)) {
            $status = 'all';
        }
        $identifier = $request->query->getString('task');
        $identifier = in_array($identifier, $this->runIdentifiers(), strict: true) ? $identifier : null;

        $rangeOffset = self::RANGE_OFFSETS[$range];
        $since = $rangeOffset !== null ? new DateTimeImmutable($rangeOffset) : null;
        $statuses = self::STATUS_FILTERS[$status];

        $totalCount = $this->runLog->count();
        $inRangeCount = $this->runLog->count($since, $identifier, $statuses);
        $problemCount = $this->runLog->count($since, $identifier, self::STATUS_FILTERS['problems']);

        $info = [new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', $totalCount, $this->translator->trans('admin_task.summary_total_runs')))];
        if ($since !== null || $identifier !== null || $statuses !== null) {
            $info[] = new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', $inRangeCount, $this->translator->trans('admin_logs.summary_in_range')));
        }
        $info[] = $this->problemTag($problemCount);

        $adminTop = new AdminTop(info: $info, actions: [
            $this->buildTaskDropdown($identifier, $range, $status, $since, $statuses),
            $this->buildStatusDropdown($status, $range, $identifier, $since),
            $this->buildRangeDropdown($range, $status, $identifier),
            $this->back($identifier),
        ]);

        return $this->render('@Task/admin/runs.html.twig', [
            'active' => 'logs',
            'runs' => $this->runLog->filtered(self::LIST_LIMIT, $since, $identifier, $statuses),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/tasks/queue', name: 'app_admin_task_queue')]
    public function queue(): Response
    {
        $health = $this->queue->health();
        $pending = $this->queue->pendingCount();

        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%s</strong>&nbsp;%s', $pending ?? '-', $this->translator->trans('admin_task.summary_pending'))),
            new AdminTopInfoHtml(sprintf(
                '<span class="tag %s is-medium">%d&nbsp;%s</span>',
                $health->workers > 0 ? 'is-success' : 'is-danger',
                $health->workers,
                $this->translator->trans('admin_task.summary_workers'),
            )),
            new AdminTopInfoHtml(sprintf(
                '<span class="tag %s is-medium">%d&nbsp;%s</span>',
                $health->schedulers > 0 ? 'is-success' : 'is-danger',
                $health->schedulers,
                $this->translator->trans('admin_task.summary_schedulers'),
            )),
        ];
        if (($health->failedMessages ?? 0) > 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-danger is-medium">%d&nbsp;%s</span>',
                $health->failedMessages,
                $this->translator->trans('admin_task.summary_failed'),
            ));
        }

        return $this->render('@Task/admin/queue.html.twig', [
            'active' => 'logs',
            'processes' => $this->queue->processes(),
            'failedMessages' => $this->queue->failedMessages(),
            'adminTop' => new AdminTop(info: $info, actions: [$this->back()]),
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/tasks/{identifier}', name: 'app_admin_task_show', requirements: ['identifier' => '[a-z0-9._\-]+'])]
    public function show(string $identifier): Response
    {
        $task = $this->registry->find($identifier);
        if ($task === null) {
            throw $this->createNotFoundException();
        }

        $cadence = $task->getCadence();
        $trigger = new CadenceTrigger($identifier, $cadence, date_default_timezone_get());
        $nextRuns = [];
        $next = new DateTimeImmutable();
        while (count($nextRuns) < self::NEXT_RUNS && ($next = $trigger->getNextRunDate($next)) !== null) {
            $nextRuns[] = $next;
        }

        $history = $this->runLog->filtered(self::HISTORY_LIMIT, null, $identifier, null);
        $last = $history[0] ?? null;
        $keptCount = $this->runLog->count(null, $identifier);

        $info = [new AdminTopInfoHtml(sprintf('<strong>%s</strong>', htmlspecialchars($identifier)))];
        if ($last !== null) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag %s is-medium">%s</span>',
                $last->status->tagVariant(),
                $this->translator->trans($last->status->label()),
            ));
        }
        $info[] = new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', $keptCount, $this->translator->trans('admin_task.summary_total_runs')));

        $adminTop = new AdminTop(info: $info, actions: [
            new AdminTopActionForm(
                label: $this->translator->trans('admin_task.action_run_now'),
                target: $this->generateUrl('app_admin_task_run_now', ['identifier' => $identifier, 'return' => 'show']),
                csrfTokenId: 'app_admin_task_run_now' . $identifier,
                icon: 'play',
                variant: 'is-warning',
            ),
            new AdminTopActionButton(
                label: $this->translator->trans('admin_task.section_runs'),
                target: $this->generateUrl('app_admin_task_runs', $this->runsParams('all', 'all', $identifier)),
                icon: 'list',
            ),
            $this->back(),
        ]);

        return $this->render('@Task/admin/show.html.twig', [
            'active' => 'logs',
            'identifier' => $identifier,
            'taskClass' => $task::class,
            'description' => $task->getDescription(),
            'cadence' => $cadence,
            'nextRuns' => $nextRuns,
            'last' => $last,
            'keptCount' => $keptCount,
            'keepDays' => RunRetention::KEEP_DAYS,
            'history' => $history,
            'stats' => $this->durationStats($history),
            'chartTile' => $this->durationChart($history),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/tasks/failed/{id}/retry', name: 'app_admin_task_failed_retry', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function retry(string $id, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('app_admin_task_failed_retry' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        if (!$this->queue->retry($id)) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRoute('app_admin_task_queue');
    }

    #[Route('/tasks/failed/{id}/delete', name: 'app_admin_task_failed_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(string $id, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('app_admin_task_failed_delete' . $id, (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }
        if (!$this->queue->delete($id)) {
            throw $this->createNotFoundException();
        }

        return $this->redirectToRoute('app_admin_task_queue');
    }

    /**
     * @return list<string>
     */
    private function runIdentifiers(): array
    {
        $identifiers = [...array_keys($this->registry->all()), ...array_keys($this->actions->all())];
        sort($identifiers);

        return $identifiers;
    }

    private function queueNeedsAttention(): bool
    {
        $health = $this->queue->health();

        return $health->workers === 0 || $health->schedulers === 0 || ($health->failedMessages ?? 0) > 0;
    }

    private function problemTag(int $problems): AdminTopInfoHtml
    {
        if ($problems === 0) {
            return new AdminTopInfoHtml(sprintf('<span class="tag is-success is-medium">%s</span>', $this->translator->trans('admin_task.summary_all_ok')));
        }

        return new AdminTopInfoHtml(sprintf(
            '<span class="tag is-danger is-medium">%d&nbsp;%s</span>',
            $problems,
            $this->translator->trans('admin_task.summary_problems'),
        ));
    }

    /**
     * @param list<FinishedRun> $history
     *
     * @return array{average: int, median: int, p95: int, max: int, ok: int}|null
     */
    private function durationStats(array $history): ?array
    {
        if ($history === []) {
            return null;
        }

        $durations = array_map(static fn(FinishedRun $run): int => $run->durationMs, $history);
        sort($durations);
        $count = count($durations);

        return [
            'average' => (int) round(array_sum($durations) / $count),
            'median' => $durations[intdiv($count, 2)],
            'p95' => $durations[min($count - 1, (int) ceil($count * 0.95) - 1)],
            'max' => $durations[$count - 1],
            'ok' => count(array_filter($history, static fn(FinishedRun $run): bool => $run->status === Status::ok)),
        ];
    }

    /**
     * @param list<FinishedRun> $history
     */
    private function durationChart(array $history): MultiSeriesChartTile
    {
        $chronological = array_reverse($history);

        return new MultiSeriesChartTile(
            title: 'admin_task.chart_duration',
            canvasId: 'taskDurationChart',
            labels: array_map(static fn(FinishedRun $run): string => $run->startedAt->format('m-d H:i'), $chronological),
            datasets: [new TileDataset(
                label: $this->translator->trans('admin_task.chart_duration_label'),
                data: array_map(static fn(FinishedRun $run): int => $run->durationMs, $chronological),
                borderColor: 'rgba(54, 162, 235, 1)',
            )],
        );
    }

    private function back(?string $identifier = null): AdminTopActionButton
    {
        return new AdminTopActionButton(
            label: $this->translator->trans('global.button_back'),
            target: $identifier === null ? $this->generateUrl('app_admin_task') : $this->generateUrl('app_admin_task_show', ['identifier' => $identifier]),
            icon: 'arrow-left',
        );
    }

    /**
     * @param list<Status>|null $statuses
     */
    private function buildTaskDropdown(?string $current, string $range, string $status, ?DateTimeImmutable $since, ?array $statuses): AdminTopActionDropdown
    {
        $options = [
            new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_task.task_filter_all'),
                target: $this->generateUrl('app_admin_task_runs', $this->runsParams($range, $status, null)),
                isActive: $current === null,
            ),
        ];
        foreach ($this->runIdentifiers() as $identifier) {
            $options[] = new AdminTopActionDropdownOption(
                label: $identifier,
                target: $this->generateUrl('app_admin_task_runs', $this->runsParams($range, $status, $identifier)),
                isActive: $identifier === $current,
                count: $this->runLog->count($since, $identifier, $statuses),
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_task.task_filter_label'),
                $current ?? $this->translator->trans('admin_task.task_filter_all'),
            ),
            options: $options,
            icon: 'gears',
        );
    }

    private function buildStatusDropdown(string $current, string $range, ?string $identifier, ?DateTimeImmutable $since): AdminTopActionDropdown
    {
        $options = [];
        foreach (self::STATUS_FILTERS as $key => $statuses) {
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_task.status_filter_' . $key),
                target: $this->generateUrl('app_admin_task_runs', $this->runsParams($range, $key, $identifier)),
                isActive: $key === $current,
                count: $statuses !== null && count($statuses) === 1 ? $this->runLog->count($since, $identifier, $statuses) : null,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_task.status_filter_label'),
                $this->translator->trans('admin_task.status_filter_' . $current),
            ),
            options: $options,
            icon: 'filter',
        );
    }

    private function buildRangeDropdown(string $current, string $status, ?string $identifier): AdminTopActionDropdown
    {
        $options = [];
        foreach (self::RANGE_OFFSETS as $key => $offset) {
            $optionSince = $offset !== null ? new DateTimeImmutable($offset) : null;
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_logs.range_' . $key),
                target: $this->generateUrl('app_admin_task_runs', $this->runsParams($key, $status, $identifier)),
                isActive: $key === $current,
                count: $this->runLog->count($optionSince, $identifier, self::STATUS_FILTERS[$status]),
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf('%s %s', $this->translator->trans('admin_logs.range_label'), $this->translator->trans('admin_logs.range_' . $current)),
            options: $options,
            icon: 'clock',
        );
    }

    /**
     * @return array<string, string>
     */
    private function runsParams(string $range, string $status, ?string $identifier): array
    {
        $params = [];
        if ($range !== self::DEFAULT_RANGE) {
            $params['range'] = $range;
        }
        if ($status !== 'all') {
            $params['status'] = $status;
        }
        if ($identifier !== null) {
            $params['task'] = $identifier;
        }

        return $params;
    }
}
