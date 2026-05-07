<?php declare(strict_types=1);

namespace App\Controller\Admin\Security;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\UrlProbingIncident;
use App\Repository\NotFoundLogRepository;
use App\Repository\UrlProbingIncidentRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use App\Service\AppStateService;
use App\Service\Security\UrlProbingAggregator;
use DateTimeImmutable;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/security/url-probing')]
final class UrlProbingController extends AbstractSecurityController implements AdminNavigationInterface, AdminTabsInterface
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
        private readonly UrlProbingIncidentRepository $incidentRepo,
        private readonly NotFoundLogRepository $notFoundLogRepo,
        private readonly AppStateService $appState,
    ) {
        parent::__construct($translator, 'url_probing');
    }

    #[Route('', name: 'app_admin_security_url_probing')]
    public function list(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SECURITY_URL_PROBING_READ);

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

        $lastProcessedId = (int) ($this->appState->get(UrlProbingAggregator::KEY_LAST_PROCESSED_ID) ?? '0');
        $cutoff = (new DateTimeImmutable())->modify('-' . UrlProbingAggregator::SETTLE_MINUTES . ' minutes');
        $pending = $this->notFoundLogRepo->countRowsAfterIdUpTo($lastProcessedId, $cutoff);
        $info[] = new AdminTopInfoHtml(sprintf(
            '<span class="tag %s is-medium">%d %s</span>',
            $pending > 0 ? 'is-warning' : 'is-light',
            $pending,
            $this->translator->trans('admin_security.summary_pending_aggregation'),
        ));

        $adminTop = new AdminTop(
            info: $info,
            actions: [
                $this->buildRangeDropdown($range),
            ],
        );

        return $this->render('admin/security/url_probing_list.html.twig', [
            'active' => 'security',
            'incidents' => $incidents,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_security_url_probing_show', requirements: ['id' => '\d+'])]
    public function show(UrlProbingIncident $incident): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SECURITY_URL_PROBING_READ);

        $deepLink = $this->generateUrl('app_admin_not_found_log', [
            'ip' => $incident->getIp(),
            'from' => $incident->getStartedAt()->format('Y-m-d H:i:s'),
            'to' => $incident->getEndedAt()->format('Y-m-d H:i:s'),
            'range' => 'all',
        ]);

        $adminTop = new AdminTop(
            info: [
                new AdminTopInfoHtml(sprintf(
                    '<strong>%s</strong>',
                    htmlspecialchars($incident->getIp(), ENT_QUOTES),
                )),
                new AdminTopInfoHtml(sprintf(
                    '<span class="tag is-warning is-medium">%d %s</span>',
                    $incident->getProbeCount(),
                    $this->translator->trans('admin_security.summary_probe_count'),
                )),
                new AdminTopInfoHtml(sprintf(
                    '<span class="has-text-grey">%d %s</span>',
                    $incident->getDistinctUrlCount(),
                    $this->translator->trans('admin_security.summary_distinct_urls'),
                )),
            ],
            actions: [
                new AdminTopActionButton(
                    label: $this->translator->trans('admin_security.button_view_raw'),
                    target: $deepLink,
                    icon: 'external-link-alt',
                ),
                new AdminTopActionButton(
                    label: $this->translator->trans('global.button_back'),
                    target: $this->generateUrl('app_admin_security_url_probing'),
                    icon: 'arrow-left',
                ),
            ],
        );

        return $this->render('admin/security/url_probing_show.html.twig', [
            'active' => 'security',
            'incident' => $incident,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    private function buildRangeDropdown(string $current): AdminTopActionDropdown
    {
        $options = [];
        foreach (array_keys(self::RANGE_OFFSETS) as $key) {
            $params = $key === self::DEFAULT_RANGE ? [] : ['range' => $key];
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_logs.range_' . $key),
                target: $this->generateUrl('app_admin_security_url_probing', $params),
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
