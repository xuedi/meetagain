<?php declare(strict_types=1);

namespace App\Controller\Admin\Logs;

use App\Activity\ActivityService;
use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\User;
use App\Repository\ActivityRepository;
use App\Repository\UserRepository;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/logs')]
final class ActivityLogController extends AbstractLogsController implements AdminNavigationInterface, AdminTabsInterface
{
    private const string DEFAULT_RANGE = '24h';
    private const int LIST_LIMIT = 5000;

    /** @var array<string, string|null> */
    private const array RANGE_OFFSETS = [
        '1h' => '-1 hour',
        '24h' => '-24 hours',
        '1w' => '-1 week',
        '1m' => '-1 month',
        'all' => null,
    ];

    public function __construct(
        TranslatorInterface $translator,
        private readonly ActivityService $activityService,
        private readonly ActivityRepository $activityRepository,
        private readonly UserRepository $userRepository,
    ) {
        parent::__construct($translator, 'activity');
    }

    #[Route('', name: 'app_admin_logs')]
    public function index(): Response
    {
        return $this->redirectToRoute('app_admin_activity_log');
    }

    #[Route('/activity', name: 'app_admin_activity_log')]
    public function list(Request $request): Response
    {
        $range = $request->query->getString('range', self::DEFAULT_RANGE);
        if (!array_key_exists($range, self::RANGE_OFFSETS)) {
            $range = self::DEFAULT_RANGE;
        }
        $rangeOffset = self::RANGE_OFFSETS[$range];
        $since = $rangeOffset !== null ? new DateTimeImmutable($rangeOffset) : null;

        $userFilterId = $request->query->getInt('user') ?: null;
        $userFilter = $userFilterId !== null ? $this->userRepository->find($userFilterId) : null;
        $resolvedUserId = $userFilter?->getId();

        $totalCount = $this->activityRepository->countAll();
        $rangeCount = $this->activityRepository->countSince($since, $resolvedUserId);
        $activities = $this->activityService->getAdminList(self::LIST_LIMIT, $since, $resolvedUserId);

        $actions = [$this->buildRangeDropdown($range, $resolvedUserId)];
        if ($userFilter !== null) {
            $actions[] = new AdminTopActionButton(
                label: $this->translator->trans(
                    'admin_logs.remove_user_filter',
                    ['%email%' => (string) $userFilter->getEmail()],
                ),
                target: $this->generateUrl('app_admin_activity_log', $this->preserveRangeParams($range)),
                icon: 'xmark',
            );
        }

        $adminTop = new AdminTop(
            info: $this->buildInfo($totalCount, $rangeCount, $since, $userFilter),
            actions: $actions,
        );

        return $this->render('admin/logs/logs_activity_list.html.twig', [
            'active' => 'logs',
            'activeLog' => 'activity',
            'activities' => $activities,
            'currentRange' => $range,
            'defaultRange' => self::DEFAULT_RANGE,
            'userFilterId' => $resolvedUserId,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/activity/{id}', name: 'app_admin_activity_log_show')]
    public function show(int $id): Response
    {
        $activity = $this->activityService->getAdminDetail($id);
        if ($activity === null) {
            throw $this->createNotFoundException();
        }

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%s</strong>',
                    $activity->getCreatedAt()->format('Y-m-d H:i:s'),
                )),
                new AdminTopInfoHtml(sprintf(
                    '<span class="tag is-light is-medium">%s</span>',
                    htmlspecialchars($activity->getType() ?? '', ENT_QUOTES | ENT_HTML5, 'UTF-8'),
                )),
            ],
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_logs.back'),
                    target: $this->generateUrl('app_admin_activity_log'),
                    icon: 'arrow-left',
                ),
            ],
        );

        return $this->render('admin/logs/logs_activity_show.html.twig', [
            'active' => 'logs',
            'activeLog' => 'activity',
            'activity' => $activity,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    /**
     * @return list<AdminTopInfoHtml>
     */
    private function buildInfo(int $totalCount, int $rangeCount, ?DateTimeImmutable $since, ?User $userFilter): array
    {
        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $totalCount,
                $this->translator->trans('admin_logs.summary_total_activities'),
            )),
        ];

        if ($since !== null || $userFilter !== null) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $rangeCount,
                $this->translator->trans('admin_logs.summary_in_range'),
            ));
        }

        return $info;
    }

    private function buildRangeDropdown(string $current, ?int $userId): AdminTopActionDropdown
    {
        $options = [];
        foreach (self::RANGE_OFFSETS as $key => $offset) {
            $params = $key === self::DEFAULT_RANGE ? [] : ['range' => $key];
            if ($userId !== null) {
                $params['user'] = $userId;
            }
            $optionSince = $offset !== null ? new DateTimeImmutable($offset) : null;
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_logs.range_' . $key),
                target: $this->generateUrl('app_admin_activity_log', $params),
                isActive: $key === $current,
                count: $this->activityRepository->countSince($optionSince, $userId),
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

    /**
     * @return array<string, mixed>
     */
    private function preserveRangeParams(string $range): array
    {
        return $range === self::DEFAULT_RANGE ? [] : ['range' => $range];
    }
}
