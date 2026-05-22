<?php declare(strict_types=1);

namespace App\Controller\Admin\Logs;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\Actions\AdminTopActionForm;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Repository\AccessDeniedLogRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use DateTimeImmutable;
use Doctrine\DBAL\Connection;
use Exception;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/logs/access-denied')]
final class AccessDeniedLogController extends AbstractLogsController implements AdminNavigationInterface, AdminTabsInterface
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
        private readonly AccessDeniedLogRepository $accessDeniedLogRepo,
        private readonly Connection $connection,
    ) {
        parent::__construct($translator, 'access_denied');
    }

    #[Route('', name: 'app_admin_access_denied_log')]
    public function list(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SECURITY_ACCESS_DENIED_READ);

        $range = $request->query->getString('range', self::DEFAULT_RANGE);
        if (!array_key_exists($range, self::RANGE_OFFSETS)) {
            $range = self::DEFAULT_RANGE;
        }
        $rangeOffset = self::RANGE_OFFSETS[$range];
        $since = $rangeOffset !== null ? new DateTimeImmutable($rangeOffset) : null;

        $ipFilter = $request->query->getString('ip', '');
        $ipFilter = $ipFilter === '' ? null : $ipFilter;
        $fromFilter = $this->parseDateParam($request->query->getString('from', ''));
        $toFilter = $this->parseDateParam($request->query->getString('to', ''));

        $recent = $this->accessDeniedLogRepo->findFiltered(200, $since, $ipFilter, $fromFilter, $toFilter);
        $totalCount = $this->accessDeniedLogRepo->countAll();
        $rangeCount = $since !== null ? $this->accessDeniedLogRepo->countSince($since) : $totalCount;

        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', $totalCount, $this->translator->trans('admin_logs.summary_total_access_denied'))),
        ];
        if ($rangeCount === 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-success is-medium">%s</span>',
                $this->translator->trans('admin_logs.summary_no_access_denied_in_range'),
            ));
        } else {
            $info[] = new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', $rangeCount, $this->translator->trans('admin_logs.summary_in_range')));
        }

        $actions = [];
        if ($totalCount > 0) {
            $actions[] = new AdminTopActionForm(
                label: $this->translator->trans('global.button_clear'),
                target: $this->generateUrl('app_admin_access_denied_log_clear'),
                csrfTokenId: 'admin_access_denied_log_clear',
                icon: 'trash',
            );
        }
        $actions[] = $this->buildRangeDropdown($range);

        $adminTop = new AdminTop(info: $info, actions: $actions);

        return $this->render('admin/logs/logs_access_denied_list.html.twig', [
            'active' => 'logs',
            'activeLog' => 'access_denied',
            'recent' => $recent,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/clear', name: 'app_admin_access_denied_log_clear', methods: ['POST'])]
    public function clear(Request $request): RedirectResponse
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SECURITY_ACCESS_DENIED_READ);

        if (!$this->isCsrfTokenValid('admin_access_denied_log_clear', (string) $request->request->get('_token'))) {
            throw new BadRequestHttpException('Invalid CSRF token.');
        }

        $this->connection->executeStatement('DELETE FROM logs_access_denied');

        return $this->redirectToRoute('app_admin_access_denied_log');
    }

    private function parseDateParam(string $value): ?DateTimeImmutable
    {
        if ($value === '') {
            return null;
        }
        try {
            return new DateTimeImmutable($value);
        } catch (Exception) {
            return null;
        }
    }

    private function buildRangeDropdown(string $current): AdminTopActionDropdown
    {
        $options = [];
        foreach (array_keys(self::RANGE_OFFSETS) as $key) {
            $params = $key === self::DEFAULT_RANGE ? [] : ['range' => $key];
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_logs.range_' . $key),
                target: $this->generateUrl('app_admin_access_denied_log', $params),
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
