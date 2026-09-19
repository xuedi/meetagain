<?php declare(strict_types=1);

namespace App\Controller\Admin\Security;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Enum\SecurityMeasure;
use App\Repository\SecurityMeasureLogRepository;
use App\Security\Permission\Attribute\PermissionAttribute;
use DateTimeImmutable;
use Psr\Clock\ClockInterface;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN')]
final class MeasureLogController extends AbstractSecurityController implements AdminNavigationInterface, AdminTabsInterface
{
    private const string DEFAULT_RANGE = '1w';
    private const int LIST_LIMIT = 500;

    /** @var array<string, string|null> */
    private const array RANGE_OFFSETS = [
        '1w' => '-1 week',
        '1m' => '-1 month',
        'all' => null,
    ];

    public function __construct(
        TranslatorInterface $translator,
        private readonly SecurityMeasureLogRepository $measureLogRepo,
        private readonly ClockInterface $clock,
    ) {
        parent::__construct($translator, 'measures');
    }

    #[Route('/admin/security/measures', name: 'app_admin_security_measures', methods: ['GET'])]
    public function list(Request $request): Response
    {
        $this->denyAccessUnlessGranted(PermissionAttribute::SYSTEM_SECURITY_INCIDENTS_READ);

        $range = $request->query->getString('range', self::DEFAULT_RANGE);
        if (!array_key_exists($range, self::RANGE_OFFSETS)) {
            $range = self::DEFAULT_RANGE;
        }
        $rangeOffset = self::RANGE_OFFSETS[$range];
        $sinceDay = $rangeOffset === null ? null : $this->clock->now()->setTime(0, 0)->modify($rangeOffset);

        $measure = SecurityMeasure::tryFrom($request->query->getString('measure'));
        $contexts = $this->measureLogRepo->blockedContexts();
        $context = $request->query->getString('context');
        $context = in_array($context, $contexts, true) ? $context : null;
        $ip = $request->query->getString('ip') ?: null;

        $filters = array_filter([
            'range' => $range === self::DEFAULT_RANGE ? null : $range,
            'measure' => $measure?->value,
            'context' => $context,
            'ip' => $ip,
        ]);

        $totalBlocks = $this->measureLogRepo->countBlocks();
        $rows = $this->measureLogRepo->recentBlocks(self::LIST_LIMIT, $sinceDay, $measure, $context, $ip);

        return $this->render('admin/security/measure_log.html.twig', [
            'active' => 'security',
            'rows' => $rows,
            'filters' => $filters,
            'ipFilter' => $ip,
            'adminTop' => $this->buildTop($filters, $totalBlocks, count($rows), $contexts, $sinceDay),
            'adminTabs' => $this->getTabs(),
        ]);
    }

    /**
     * @param array<string, string> $filters
     * @param list<string> $contexts
     */
    private function buildTop(array $filters, int $totalBlocks, int $shownBlocks, array $contexts, ?DateTimeImmutable $sinceDay): AdminTop
    {
        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', $totalBlocks, $this->translator->trans('admin_security.summary_total_measure_blocks'))),
        ];
        if ($shownBlocks === 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-success is-medium">%s</span>',
                $this->translator->trans('admin_security.summary_no_measure_blocks_in_range'),
            ));
        } elseif ($sinceDay !== null || count($filters) > 0) {
            $info[] = new AdminTopInfoHtml(sprintf('<strong>%d</strong>&nbsp;%s', $shownBlocks, $this->translator->trans('admin_security.summary_in_range')));
        }

        $actions = [];
        if (isset($filters['ip'])) {
            $actions[] = new AdminTopActionButton(
                label: $this->translator->trans('admin_security.remove_ip_filter', ['%ip%' => $filters['ip']]),
                target: $this->listUrl($filters, 'ip', null),
                icon: 'xmark',
            );
        }
        $actions[] = $this->buildMeasureDropdown($filters);
        if ($contexts !== []) {
            $actions[] = $this->buildContextDropdown($filters, $contexts);
        }
        $actions[] = $this->buildRangeDropdown($filters);
        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('admin_security.button_security_measures'),
            target: $this->generateUrl('app_admin_system_security'),
            icon: 'shield-halved',
        );

        return new AdminTop(info: $info, actions: $actions);
    }

    /**
     * @param array<string, string> $filters
     */
    private function buildRangeDropdown(array $filters): AdminTopActionDropdown
    {
        $current = $filters['range'] ?? self::DEFAULT_RANGE;
        $options = [];
        foreach (array_keys(self::RANGE_OFFSETS) as $key) {
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_logs.range_' . $key),
                target: $this->listUrl($filters, 'range', $key === self::DEFAULT_RANGE ? null : $key),
                isActive: $key === $current,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf('%s %s', $this->translator->trans('admin_logs.range_label'), $this->translator->trans('admin_logs.range_' . $current)),
            options: $options,
            icon: 'clock',
        );
    }

    /**
     * @param array<string, string> $filters
     */
    private function buildMeasureDropdown(array $filters): AdminTopActionDropdown
    {
        $current = isset($filters['measure']) ? SecurityMeasure::from($filters['measure']) : null;
        $options = [
            new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_security.filter_all'),
                target: $this->listUrl($filters, 'measure', null),
                isActive: $current === null,
            ),
        ];
        foreach (SecurityMeasure::cases() as $measure) {
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans($measure->labelKey()),
                target: $this->listUrl($filters, 'measure', $measure->value),
                isActive: $measure === $current,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_security.measure_filter_label'),
                $current === null ? $this->translator->trans('admin_security.filter_all') : $this->translator->trans($current->labelKey()),
            ),
            options: $options,
            icon: 'shield-halved',
        );
    }

    /**
     * @param array<string, string> $filters
     * @param list<string> $contexts
     */
    private function buildContextDropdown(array $filters, array $contexts): AdminTopActionDropdown
    {
        $current = $filters['context'] ?? null;
        $options = [
            new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_security.filter_all'),
                target: $this->listUrl($filters, 'context', null),
                isActive: $current === null,
            ),
        ];
        foreach ($contexts as $context) {
            $options[] = new AdminTopActionDropdownOption(
                label: $context,
                target: $this->listUrl($filters, 'context', $context),
                isActive: $context === $current,
            );
        }

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_security.context_filter_label'),
                $current ?? $this->translator->trans('admin_security.filter_all'),
            ),
            options: $options,
            icon: 'file-lines',
        );
    }

    /**
     * @param array<string, string> $filters
     */
    private function listUrl(array $filters, string $key, ?string $value): string
    {
        unset($filters[$key]);
        if ($value !== null) {
            $filters[$key] = $value;
        }

        return $this->generateUrl('app_admin_security_measures', $filters);
    }
}
