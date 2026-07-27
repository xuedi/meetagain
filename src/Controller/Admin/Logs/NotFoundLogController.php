<?php declare(strict_types=1);

namespace App\Controller\Admin\Logs;

use App\Admin\Dashboard\ChartTile;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\Actions\AdminTopActionForm;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\NotFoundLog;
use App\Entity\SuspiciousUrl;
use App\Repository\NotFoundLogRepository;
use App\Repository\SuspiciousUrlRepository;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/logs/404')]
final class NotFoundLogController extends AbstractLogsController implements AdminNavigationInterface, AdminTabsInterface
{
    private const string DEFAULT_RANGE = '24h';
    private const int TOP_LIMIT = 25;
    private const int CHART_BUCKETS = 30;

    /** @var array<string, string|null> */
    private const array RANGE_OFFSETS = [
        '24h' => '-24 hours',
        '1w' => '-1 week',
        '1m' => '-1 month',
        'all' => null,
    ];

    public function __construct(
        TranslatorInterface $translator,
        private readonly NotFoundLogRepository $notFoundLogRepo,
        private readonly SuspiciousUrlRepository $suspiciousUrlRepo,
        private readonly EntityManagerInterface $entityManager,
        private readonly Connection $connection,
    ) {
        parent::__construct($translator, 'not_found');
    }

    #[Route('', name: 'app_admin_not_found_log')]
    public function list(Request $request): Response
    {
        $range = $this->resolveRange($request);
        $since = $this->resolveSince($range);

        $ipFilter = $request->query->getString('ip', '');
        $ipFilter = $ipFilter === '' ? null : $ipFilter;
        $fromFilter = $this->parseDateParam($request->query->getString('from', ''));
        $toFilter = $this->parseDateParam($request->query->getString('to', ''));

        $recent = $this->notFoundLogRepo->findFiltered(200, $since, $ipFilter, $fromFilter, $toFilter);
        $totalCount = $this->notFoundLogRepo->countAll();
        $rangeCount = $since !== null ? $this->notFoundLogRepo->countSince($since) : $totalCount;

        $actions = [];
        if ($totalCount > 0) {
            $actions[] = new AdminTopActionForm(
                label: $this->translator->trans('global.button_clear'),
                target: $this->generateUrl('app_admin_not_found_log_clear'),
                csrfTokenId: 'admin_not_found_log_clear',
                icon: 'trash',
            );
        }
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('admin_logs.action_statistics'),
            target: $this->generateUrl('app_admin_not_found_log_statistics', $range === self::DEFAULT_RANGE ? [] : ['range' => $range]),
            icon: 'chart-simple',
        );
        $actions[] = $this->buildRangeDropdown('app_admin_not_found_log', $range);

        $adminTop = new AdminTop(info: $this->buildInfo($totalCount, $rangeCount), actions: $actions);

        return $this->render('admin/logs/logs_notFound_list.html.twig', [
            'active' => 'logs',
            'activeLog' => '404',
            'recent' => $recent,
            'suspiciousUrls' => $this->suspiciousUrlRepo->findAllUrls(),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/statistics', name: 'app_admin_not_found_log_statistics')]
    public function statistics(Request $request): Response
    {
        $range = $this->resolveRange($request);
        $since = $this->resolveSince($range);

        $totalCount = $this->notFoundLogRepo->countAll();
        $rangeCount = $since !== null ? $this->notFoundLogRepo->countSince($since) : $totalCount;

        $suspicious = $this->suspiciousUrlRepo->findAllOrdered();
        $suspiciousCounts = $this->notFoundLogRepo->countByUrls(
            array_values(array_filter(array_map(static fn(SuspiciousUrl $entry): ?string => $entry->getUrl(), $suspicious))),
            $since,
        );
        $suspiciousHits = array_sum($suspiciousCounts);

        $adminTop = new AdminTop(
            info: $this->buildStatisticsInfo($totalCount, $rangeCount, $since, $suspiciousHits),
            actions: [
                $this->buildRangeDropdown('app_admin_not_found_log_statistics', $range),
                new AdminTopActionButton(
                    label: $this->translator->trans('global.button_back'),
                    target: $this->generateUrl('app_admin_not_found_log', $range === self::DEFAULT_RANGE ? [] : ['range' => $range]),
                    icon: 'arrow-left',
                ),
            ],
        );

        return $this->render('admin/logs/logs_notFound_statistics.html.twig', [
            'active' => 'logs',
            'activeLog' => '404',
            'range' => $range,
            'topUrls' => $this->notFoundLogRepo->getTopUrls(self::TOP_LIMIT, $since),
            'topIps' => $this->notFoundLogRepo->getTopIps(self::TOP_LIMIT, $since),
            'topReferers' => $this->notFoundLogRepo->getTopReferers(self::TOP_LIMIT, $since),
            'topUserAgents' => $this->notFoundLogRepo->getTopUserAgents(self::TOP_LIMIT, $since),
            'suspicious' => $suspicious,
            'suspiciousCounts' => $suspiciousCounts,
            'suspiciousHits' => $suspiciousHits,
            'chartTile' => $this->buildChartTile($range, $since),
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/clear', name: 'app_admin_not_found_log_clear', methods: ['POST'])]
    public function clear(Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_not_found_log_clear', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->connection->executeStatement('DELETE FROM logs_not_found');

        return $this->redirectToRoute('app_admin_not_found_log');
    }

    #[Route('/{id}/suspicious', name: 'app_admin_not_found_log_suspicious_toggle', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function toggleSuspicious(NotFoundLog $log, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_not_found_log_suspicious' . $log->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $url = (string) $log->getUrl();
        $existing = $this->suspiciousUrlRepo->findOneByUrl($url);
        if ($existing !== null) {
            $this->entityManager->remove($existing);
        } else {
            $this->entityManager->persist((new SuspiciousUrl())->setUrl($url)->setCreatedAt(new DateTimeImmutable()));
        }
        $this->entityManager->flush();

        return $this->redirectToRoute('app_admin_not_found_log', $this->listQueryParams($request));
    }

    #[Route('/suspicious/{id}/remove', name: 'app_admin_not_found_log_suspicious_remove', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function removeSuspicious(SuspiciousUrl $suspiciousUrl, Request $request): RedirectResponse
    {
        if (!$this->isCsrfTokenValid('admin_not_found_log_suspicious_remove' . $suspiciousUrl->getId(), (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->entityManager->remove($suspiciousUrl);
        $this->entityManager->flush();

        $range = $this->resolveRange($request);

        return $this->redirectToRoute('app_admin_not_found_log_statistics', $range === self::DEFAULT_RANGE ? [] : ['range' => $range]);
    }

    /**
     * @return list<AdminTopInfoHtml>
     */
    private function buildInfo(int $totalCount, int $rangeCount): array
    {
        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', $totalCount, $this->translator->trans('admin_logs.summary_total_404'))),
        ];

        if ($rangeCount === 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-success is-medium">%s</span>',
                $this->translator->trans('admin_logs.summary_no_404_in_range'),
            ));

            return $info;
        }

        $info[] = new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', $rangeCount, $this->translator->trans('admin_logs.summary_in_range')));

        return $info;
    }

    /**
     * @return list<AdminTopInfoHtml>
     */
    private function buildStatisticsInfo(int $totalCount, int $rangeCount, ?DateTimeImmutable $since, int $suspiciousHits): array
    {
        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', $rangeCount, $this->translator->trans('admin_logs.summary_in_range'))),
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $this->notFoundLogRepo->countDistinctUrls($since),
                $this->translator->trans('admin_logs.summary_distinct_urls'),
            )),
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $this->notFoundLogRepo->countDistinctIps($since),
                $this->translator->trans('admin_logs.summary_distinct_ips'),
            )),
        ];

        if ($suspiciousHits > 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-danger is-medium">%d&nbsp;%s</span>',
                $suspiciousHits,
                $this->translator->trans('admin_logs.summary_suspicious_hits'),
            ));
        }

        $info[] = new AdminTopInfoHtml(sprintf(
            '<span class="has-text-grey">%d %s</span>',
            $totalCount,
            $this->translator->trans('admin_logs.summary_total_404'),
        ));

        return $info;
    }

    private function buildChartTile(string $range, ?DateTimeImmutable $since): ChartTile
    {
        $buckets = $range === '24h'
            ? $this->notFoundLogRepo->getHourlyCounts(24, $since)
            : $this->notFoundLogRepo->getDailyCounts(self::CHART_BUCKETS, $since);

        $dataset = [];
        foreach ($buckets as $bucket) {
            $dataset[] = ['x' => $bucket['bucket'], 'y' => $bucket['number']];
        }

        return new ChartTile(
            title: $range === '24h' ? 'admin_logs.chart_404_per_hour' : 'admin_logs.chart_404_per_day',
            canvasId: 'notFoundStatisticsChart',
            dataset: $dataset,
            color: 'rgba(255, 99, 132, 0.5)',
        );
    }

    private function resolveRange(Request $request): string
    {
        $range = $request->query->getString('range', self::DEFAULT_RANGE);

        return array_key_exists($range, self::RANGE_OFFSETS) ? $range : self::DEFAULT_RANGE;
    }

    private function resolveSince(string $range): ?DateTimeImmutable
    {
        $offset = self::RANGE_OFFSETS[$range];

        return $offset !== null ? new DateTimeImmutable($offset) : null;
    }

    /**
     * @return array<string, string>
     */
    private function listQueryParams(Request $request): array
    {
        $params = [];
        foreach (['range', 'ip', 'from', 'to'] as $key) {
            $value = $request->query->getString($key, '');
            if ($value !== '') {
                $params[$key] = $value;
            }
        }

        return $params;
    }

    private function parseDateParam(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (\Exception) {
            return null;
        }
    }

    private function buildRangeDropdown(string $route, string $current): AdminTopActionDropdown
    {
        $options = [];
        foreach (array_keys(self::RANGE_OFFSETS) as $key) {
            $params = $key === self::DEFAULT_RANGE ? [] : ['range' => $key];
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_logs.range_' . $key),
                target: $this->generateUrl($route, $params),
                isActive: $key === $current,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf('%s %s', $this->translator->trans('admin_logs.range_label'), $this->translator->trans('admin_logs.range_' . $current)),
            options: $options,
            icon: 'clock',
        );
    }
}
