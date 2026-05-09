<?php declare(strict_types=1);

namespace App\Controller\Admin\Security;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\Incident;
use App\Repository\AccessDeniedLogRepository;
use App\Repository\IncidentRepository;
use App\Repository\NotFoundLogRepository;
use App\Repository\RateLimitLogRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\AppStateService;
use App\Service\Security\Incident\Sources\AccessDeniedIncidentSource;
use App\Service\Security\Incident\Sources\RateLimitIncidentSource;
use App\Service\Security\Incident\Sources\UrlProbingIncidentSource;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/security/incidents')]
final class IncidentsController extends AbstractSecurityController implements AdminNavigationInterface, AdminTabsInterface
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
        private readonly IncidentRepository $incidentRepo,
        private readonly NotFoundLogRepository $notFoundLogRepo,
        private readonly AccessDeniedLogRepository $accessDeniedLogRepo,
        private readonly RateLimitLogRepository $rateLimitLogRepo,
        private readonly AppStateService $appState,
    ) {
        parent::__construct($translator, 'incidents');
    }

    #[Route('', name: 'app_admin_security_incidents')]
    public function list(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SECURITY_INCIDENTS_READ);

        $range = $request->query->getString('range', self::DEFAULT_RANGE);
        if (!array_key_exists($range, self::RANGE_OFFSETS)) {
            $range = self::DEFAULT_RANGE;
        }
        $rangeOffset = self::RANGE_OFFSETS[$range];
        $since = $rangeOffset !== null ? new DateTimeImmutable($rangeOffset) : null;

        $incidents = $this->incidentRepo->getRecent(200, $since);
        $totalCount = $this->incidentRepo->countAll();
        $rangeCount = $since !== null ? $this->incidentRepo->countSince($since) : $totalCount;

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $totalCount,
                $this->translator->trans('admin_security.summary_total_incidents'),
            )),
        ];
        if ($rangeCount === 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-success is-medium">%s</span>',
                $this->translator->trans('admin_security.summary_no_incidents_in_range'),
            ));
        } else {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $rangeCount,
                $this->translator->trans('admin_security.summary_in_range'),
            ));
        }

        $info[] = new AdminTopInfoHtml($this->buildPendingPerSourceHtml());

        $adminTop = new AdminTop(
            info: $info,
            actions: [$this->buildRangeDropdown($range)],
        );

        return $this->render('admin/security/incidents_list.html.twig', [
            'active' => 'security',
            'incidents' => $incidents,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_security_incidents_show', requirements: ['id' => '\d+'])]
    public function show(Incident $incident): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SECURITY_INCIDENTS_READ);

        $params = [
            'ip' => $incident->getIp(),
            'from' => $incident->getStartedAt()->format('Y-m-d H:i:s'),
            'to' => $incident->getEndedAt()->format('Y-m-d H:i:s'),
            'range' => 'all',
        ];

        $severity = $incident->getSeverity();

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%s</strong>',
                    htmlspecialchars($incident->getIp(), ENT_QUOTES),
                )),
                new AdminTopInfoHtml(sprintf(
                    '<span class="tag %s is-medium">%s</span>',
                    $severity->tagClass(),
                    $this->translator->trans($severity->label()),
                )),
                new AdminTopInfoHtml(sprintf(
                    '<span class="has-text-grey">%d %s</span>',
                    $incident->getTotalHits(),
                    $this->translator->trans('admin_security.col_total_hits'),
                )),
            ],
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_security.button_view_raw_404'),
                    target: $this->generateUrl('app_admin_not_found_log', $params),
                    icon: 'external-link-alt',
                ),
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_security.button_view_raw_access_denied'),
                    target: $this->generateUrl('app_admin_access_denied_log', $params),
                    icon: 'external-link-alt',
                ),
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_security.button_view_raw_rate_limit'),
                    target: $this->generateUrl('app_admin_security_rate_limiting', $params),
                    icon: 'external-link-alt',
                ),
                new AdminTopActionButton(
                    label: $this->translator->trans('global.button_back'),
                    target: $this->generateUrl('app_admin_security_incidents'),
                    icon: 'arrow-left',
                ),
            ],
        );

        return $this->render('admin/security/incidents_show.html.twig', [
            'active' => 'security',
            'incident' => $incident,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    private function buildPendingPerSourceHtml(): string
    {
        $now = new DateTimeImmutable();

        $probingLastId = (int) ($this->appState->get(UrlProbingIncidentSource::KEY_LAST_PROCESSED_ID) ?? '0');
        $probingCutoff = $now->modify('-' . UrlProbingIncidentSource::SETTLE_MINUTES . ' minutes');
        $probingPending = $this->notFoundLogRepo->countRowsAfterIdUpTo($probingLastId, $probingCutoff);

        $accessDeniedLastId = (int) ($this->appState->get(AccessDeniedIncidentSource::KEY_LAST_PROCESSED_ID) ?? '0');
        $accessDeniedCutoff = $now->modify('-' . AccessDeniedIncidentSource::SETTLE_MINUTES . ' minutes');
        $accessDeniedPending = $this->accessDeniedLogRepo->countRowsAfterIdUpTo($accessDeniedLastId, $accessDeniedCutoff);

        $rateLimitLastId = (int) ($this->appState->get(RateLimitIncidentSource::KEY_LAST_PROCESSED_ID) ?? '0');
        $rateLimitCutoff = $now->modify('-' . RateLimitIncidentSource::SETTLE_MINUTES . ' minutes');
        $rateLimitPending = $this->rateLimitLogRepo->countRowsAfterIdUpTo($rateLimitLastId, $rateLimitCutoff);

        $totalPending = $probingPending + $accessDeniedPending + $rateLimitPending;
        $tagClass = $totalPending > 0 ? 'is-warning' : 'is-light';

        return sprintf(
            '<span class="tag %s is-medium">%d %s | %d %s | %d %s</span>',
            $tagClass,
            $accessDeniedPending,
            $this->translator->trans('admin_security.pending_access_denied'),
            $rateLimitPending,
            $this->translator->trans('admin_security.pending_rate_limit'),
            $probingPending,
            $this->translator->trans('admin_security.pending_probing'),
        );
    }

    private function buildRangeDropdown(string $current): AdminTopActionDropdown
    {
        $options = [];
        foreach (array_keys(self::RANGE_OFFSETS) as $key) {
            $params = $key === self::DEFAULT_RANGE ? [] : ['range' => $key];
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_logs.range_' . $key),
                target: $this->generateUrl('app_admin_security_incidents', $params),
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
