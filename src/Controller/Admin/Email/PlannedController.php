<?php

declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Admin\Navigation\AdminNavigationInterface;
use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Admin\Top\Infos\AdminTopInfoText;
use App\Emails\EmailGuardOutcome;
use App\Emails\EmailInterface;
use App\Emails\Guard\EmailGuardEvaluator;
use App\Emails\ScheduledEmailInterface;
use App\Service\Email\PlannedEmailService;
use DateTimeImmutable;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/email/planned')]
final class PlannedController extends AbstractEmailController implements AdminNavigationInterface, AdminTabsInterface
{
    /**
     * @param iterable<EmailInterface> $emailTypes
     */
    public function __construct(
        TranslatorInterface $translator,
        private readonly PlannedEmailService $plannedEmailService,
        private readonly EmailGuardEvaluator $guardEvaluator,
        #[AutowireIterator(EmailInterface::class)]
        private readonly iterable $emailTypes,
    ) {
        parent::__construct($translator, 'planned');
    }

    #[Route('', name: 'app_admin_email_planned')]
    public function list(Request $request): Response
    {
        $onlyExpected = $request->query->getBoolean('onlyExpected');

        $from = new DateTimeImmutable();
        $to = $from->modify('+14 days');
        $items = $this->plannedEmailService->getPlannedItems($from, $to);

        if ($onlyExpected) {
            $items = array_values(array_filter($items, static fn($item): bool => (int) $item->expectedRecipients > 0));
        }

        $modeKey = $onlyExpected ? 'admin_email_planned.mode_only_expected' : 'admin_email_planned.mode_all';

        $toggleAction = $onlyExpected
            ? new AdminTopActionButton(
                label: $this->translator->trans('admin_email_planned.filter_show_all'),
                target: $this->generateUrl('app_admin_email_planned'),
                icon: 'list',
            )
            : new AdminTopActionButton(
                label: $this->translator->trans('admin_email_planned.filter_only_expected'),
                target: $this->generateUrl('app_admin_email_planned', ['onlyExpected' => 1]),
                icon: 'filter',
            );

        $adminTop = new AdminTop(info: [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                count($items),
                $this->translator->trans('admin_email_planned.summary_planned'),
            )),
            new AdminTopInfoText($this->translator->trans($modeKey)),
        ], actions: [$toggleAction]);

        return $this->render('admin/email/planned/list.html.twig', [
            'active' => 'email',
            'activeSection' => 'planned',
            'items' => $items,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{key}', name: 'app_admin_email_planned_detail', methods: ['GET'])]
    public function detail(string $key, Request $request): Response
    {
        $from = new DateTimeImmutable();
        $to = $from->modify('+14 days');
        $items = $this->plannedEmailService->getPlannedItems($from, $to);

        $matchedItem = null;
        foreach ($items as $item) {
            if ($item->getKey() !== $key) {
                continue;
            }

            $matchedItem = $item;
            break;
        }
        if ($matchedItem === null) {
            throw $this->createNotFoundException('Planned email not found.');
        }

        $email = null;
        foreach ($this->emailTypes as $candidate) {
            if ($candidate->getIdentifier() !== $matchedItem->mailType) {
                continue;
            }

            $email = $candidate;
            break;
        }
        if (!$email instanceof ScheduledEmailInterface) {
            throw $this->createNotFoundException('Email type not scheduled.');
        }

        $recipients = [];
        foreach ($email->getPreviewContexts($matchedItem->expectedTime) as $dueContext) {
            foreach ($dueContext->potentialRecipients as $user) {
                $recipients[] = [
                    'user' => $user,
                    'context' => array_merge($dueContext->data, ['user' => $user, 'recipient' => $user]),
                ];
            }
        }

        $rows = [];
        $passCount = 0;
        $skipCount = 0;
        $errorCount = 0;
        foreach ($recipients as $entry) {
            $result = $this->guardEvaluator->evaluate($email, $entry['context']);
            $rows[] = [
                'user' => $entry['user'],
                'context' => $entry['context'],
                'outcome' => $result->outcome,
            ];
            $passCount += $result->outcome === EmailGuardOutcome::Pass ? 1 : 0;
            $skipCount += $result->outcome === EmailGuardOutcome::Skip ? 1 : 0;
            $errorCount += $result->outcome === EmailGuardOutcome::Error ? 1 : 0;
        }

        $userId = $request->query->getInt('user');
        $previewUser = null;
        $previewContext = null;
        foreach ($rows as $row) {
            if ($userId <= 0 || $row['user']->getId() !== $userId) {
                continue;
            }

            $previewUser = $row['user'];
            $previewContext = $row['context'];
            break;
        }

        $outcomeFilter = $request->query->getString('outcome', 'pass');
        if (!in_array($outcomeFilter, ['all', 'pass', 'skip', 'error'], true)) {
            $outcomeFilter = 'pass';
        }
        $filteredRows = match ($outcomeFilter) {
            'all' => $rows,
            'pass' => array_values(array_filter($rows, static fn(array $r): bool => $r['outcome'] === EmailGuardOutcome::Pass)),
            'skip' => array_values(array_filter($rows, static fn(array $r): bool => $r['outcome'] === EmailGuardOutcome::Skip)),
            'error' => array_values(array_filter($rows, static fn(array $r): bool => $r['outcome'] === EmailGuardOutcome::Error)),
        };

        $results = $previewContext !== null ? $this->guardEvaluator->evaluateAll($email, $previewContext) : [];
        $rules = $email->getGuardRules();

        $info = [
            new AdminTopInfoHtml(sprintf('<strong>%s</strong>', htmlspecialchars(
                $matchedItem->mailType,
                ENT_QUOTES | ENT_HTML5,
                'UTF-8',
            ))),
            new AdminTopInfoHtml(sprintf('<strong>%s</strong>', $matchedItem->expectedTime->format('Y-m-d H:i'))),
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $passCount,
                $this->translator->trans('admin_email_planned.summary_pass'),
            )),
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $skipCount,
                $this->translator->trans('admin_email_planned.summary_skip'),
            )),
        ];
        if ($errorCount > 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-danger is-medium"><strong>%d</strong>&nbsp;%s</span>',
                $errorCount,
                $this->translator->trans('admin_email_planned.summary_error'),
            ));
        }

        $totalCount = count($rows);
        $outcomeOptions = [
            ['key' => 'all', 'label' => 'admin_email_planned.outcome_filter_all', 'count' => $totalCount],
            ['key' => 'pass', 'label' => 'admin_email_planned.outcome_filter_pass', 'count' => $passCount],
            ['key' => 'skip', 'label' => 'admin_email_planned.outcome_filter_skip', 'count' => $skipCount],
            ['key' => 'error', 'label' => 'admin_email_planned.outcome_filter_error', 'count' => $errorCount],
        ];
        $dropdownOptions = [];
        $activeLabel = '';
        foreach ($outcomeOptions as $option) {
            $params = ['outcome' => $option['key']];
            if ($previewUser !== null) {
                $params['user'] = $previewUser->getId();
            }
            $isActive = $option['key'] === $outcomeFilter;
            if ($isActive) {
                $activeLabel = $this->translator->trans($option['label']);
            }
            $dropdownOptions[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans($option['label']),
                target: $this->generateUrl('app_admin_email_planned_detail', ['key' => $key] + $params),
                isActive: $isActive,
                count: $option['count'],
            );
        }

        $adminTop = new AdminTop(info: $info, actions: [
            new AdminTopActionDropdown(
                label: sprintf(
                    '%s %s',
                    $this->translator->trans('admin_email_planned.outcome_filter_label'),
                    $activeLabel,
                ),
                options: $dropdownOptions,
                icon: 'filter',
            ),
            new AdminTopActionButton(
                label: $this->translator->trans('global.button_back'),
                target: $this->generateUrl('app_admin_email_planned'),
                icon: 'arrow-left',
            ),
        ]);

        return $this->render('admin/email/planned/detail.html.twig', [
            'active' => 'email',
            'activeSection' => 'planned',
            'item' => $matchedItem,
            'rows' => $filteredRows,
            'rules' => $rules,
            'results' => $results,
            'previewUser' => $previewUser,
            'detailKey' => $key,
            'outcomeFilter' => $outcomeFilter,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }
}
