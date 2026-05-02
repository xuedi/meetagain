<?php declare(strict_types=1);

namespace App\Controller\Admin\Logs;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Repository\NotFoundLogRepository;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/logs/404')]
final class NotFoundLogController extends AbstractLogsController implements AdminNavigationInterface, AdminTabsInterface
{
    private const string DEFAULT_RANGE = '24h';

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
    ) {
        parent::__construct($translator, 'not_found');
    }

    #[Route('', name: 'app_admin_not_found_log')]
    public function list(Request $request): Response
    {
        $range = $request->query->getString('range', self::DEFAULT_RANGE);
        if (!array_key_exists($range, self::RANGE_OFFSETS)) {
            $range = self::DEFAULT_RANGE;
        }
        $rangeOffset = self::RANGE_OFFSETS[$range];
        $since = $rangeOffset !== null ? new DateTimeImmutable($rangeOffset) : null;

        $top = $this->notFoundLogRepo->getTop100($since);
        $recent = $this->notFoundLogRepo->getRecent(200, $since);
        $totalCount = $this->notFoundLogRepo->countAll();
        $rangeCount = $since !== null
            ? $this->notFoundLogRepo->countSince($since)
            : $totalCount;

        $adminTop = new AdminTop(
            info: $this->buildInfo($totalCount, $rangeCount),
            actions: [$this->buildRangeDropdown($range)],
        );

        return $this->render('admin/logs/logs_notFound_list.html.twig', [
            'active' => 'logs',
            'activeLog' => '404',
            'list' => $top,
            'recent' => $recent,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    /**
     * @return list<AdminTopInfoHtml>
     */
    private function buildInfo(int $totalCount, int $rangeCount): array
    {
        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $totalCount,
                $this->translator->trans('admin_logs.summary_total_404'),
            )),
        ];

        if ($rangeCount === 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-success is-medium">%s</span>',
                $this->translator->trans('admin_logs.summary_no_404_in_range'),
            ));

            return $info;
        }

        $info[] = new AdminTopInfoHtml(sprintf(
            '<strong>%d</strong>&nbsp;%s',
            $rangeCount,
            $this->translator->trans('admin_logs.summary_in_range'),
        ));

        return $info;
    }

    private function buildRangeDropdown(string $current): AdminTopActionDropdown
    {
        $options = [];
        foreach (array_keys(self::RANGE_OFFSETS) as $key) {
            $params = $key === self::DEFAULT_RANGE ? [] : ['range' => $key];
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_logs.range_' . $key),
                target: $this->generateUrl('app_admin_not_found_log', $params),
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
