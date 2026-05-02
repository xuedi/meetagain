<?php declare(strict_types=1);

namespace App\Controller\Admin\Email;

use App\Admin\Tabs\AdminTabsInterface;
use App\Admin\Top\Actions\AdminTopActionButton;
use App\Admin\Top\Actions\AdminTopActionDropdown;
use App\Admin\Top\Actions\AdminTopActionDropdownOption;
use App\Admin\Top\AdminTop;
use App\Admin\Top\Infos\AdminTopInfoHtml;
use App\Entity\EmailQueue;
use App\Enum\EmailQueueStatus;
use App\Enum\EmailType;
use App\Repository\EmailQueueRepository;
use App\Service\Email\Delivery\EmailDeliveryProviderInterface;
use App\Service\Email\Delivery\EmailDeliveryStatusSyncService;
use App\Service\Email\EmailTemplateService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Contracts\Translation\TranslatorInterface;

#[IsGranted('ROLE_ADMIN'), Route('/admin/email/sendlog')]
final class SendlogController extends AbstractEmailController implements AdminTabsInterface
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

    /** @var list<EmailQueueStatus> */
    private const array PROBLEM_STATUSES = [EmailQueueStatus::Failed, EmailQueueStatus::Late];

    public function __construct(
        TranslatorInterface $translator,
        private readonly EmailQueueRepository $emailQueueRepo,
        private readonly EmailDeliveryProviderInterface $provider,
        private readonly EmailDeliveryStatusSyncService $syncService,
        private readonly EmailTemplateService $templateService,
        private readonly EntityManagerInterface $em,
    ) {
        parent::__construct($translator, 'sendlog');
    }

    #[Route('', name: 'app_admin_email_sendlog')]
    public function sendlog(Request $request): Response
    {
        $range = $request->query->getString('range', self::DEFAULT_RANGE);
        if (!array_key_exists($range, self::RANGE_OFFSETS)) {
            $range = self::DEFAULT_RANGE;
        }
        $rangeOffset = self::RANGE_OFFSETS[$range];
        $since = $rangeOffset !== null ? new DateTimeImmutable($rangeOffset) : null;

        $templateValue = $request->query->getString('template');
        $template = $templateValue !== '' ? EmailType::tryFrom($templateValue) : null;

        $recipient = trim($request->query->getString('recipient'));
        $recipient = $recipient !== '' ? $recipient : null;

        $emails = $this->emailQueueRepo->findFiltered(self::LIST_LIMIT, $since, $template, $recipient);
        $totalCount = $this->emailQueueRepo->countAll();
        $rangeCount = $this->emailQueueRepo->countFiltered($since, $template, $recipient);
        $problemCount = $this->emailQueueRepo->countFiltered($since, $template, $recipient, self::PROBLEM_STATUSES);

        $actions = [
            $this->buildTemplateDropdown($template, $range, $recipient, $since),
            $this->buildRangeDropdown($range, $template, $recipient),
        ];
        if ($recipient !== null) {
            $actions[] = new AdminTopActionButton(
                label: $this->translator->trans('admin_email_sendlog.remove_recipient_filter', ['%email%' => $recipient]),
                target: $this->generateUrl('app_admin_email_sendlog', $this->preserveParams($range, $template?->value, null)),
                icon: 'xmark',
            );
        }

        $adminTop = new AdminTop(
            info: $this->buildInfo($totalCount, $rangeCount, $problemCount, $since, $template, $recipient),
            actions: $actions,
        );

        return $this->render('admin/email/sendlog/list.html.twig', [
            'active' => 'email',
            'emails' => $emails,
            'providerAvailable' => $this->provider->isAvailable(),
            'currentRange' => $range,
            'defaultRange' => self::DEFAULT_RANGE,
            'currentTemplate' => $template?->value,
            'recipientFilter' => $recipient,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}', name: 'app_admin_email_sendlog_show', requirements: ['id' => '\d+'])]
    public function show(EmailQueue $email): Response
    {
        $renderedSubject = null;
        $renderedBody = null;
        $renderError = null;

        $templateType = $email->getTemplate();
        if ($templateType !== null) {
            try {
                $content = $this->templateService->getTemplateContent(
                    $templateType,
                    $email->getLang() ?? 'en',
                );
                $context = $email->getContext();
                $renderedSubject = $this->templateService->renderContent($content['subject'], $context);
                $renderedBody = $this->templateService->renderContent($content['body'], $context);
            } catch (RuntimeException $e) {
                $renderError = $e->getMessage();
            }
        }

        $adminTop = new AdminTop(
            info: $this->buildShowInfo($email),
            actions: $this->buildShowActions($email),
        );

        return $this->render('admin/email/sendlog/show.html.twig', [
            'active' => 'email',
            'email' => $email,
            'renderedSubject' => $renderedSubject,
            'renderedBody' => $renderedBody,
            'renderError' => $renderError,
            'adminTop' => $adminTop,
            'adminTabs' => $this->getTabs(),
        ]);
    }

    #[Route('/{id}/clear-cap', name: 'app_admin_email_sendlog_clear_cap', requirements: ['id' => '\d+'])]
    public function clearCap(EmailQueue $email): Response
    {
        $email->setMaxSendBy(null);
        $email->setStatus(EmailQueueStatus::Pending);
        $email->setErrorMessage(null);
        $this->em->flush();

        $this->addFlash('success', $this->translator->trans('admin_email_sendlog.flash_cap_cleared'));

        return $this->redirectToRoute('app_admin_email_sendlog_show', ['id' => $email->getId()]);
    }

    #[Route('/sync', name: 'app_admin_email_sendlog_sync', methods: ['POST'])]
    public function sync(): Response
    {
        $result = $this->syncService->syncPending(200);

        if ($result->available) {
            $this->addFlash('success', $this->translator->trans('admin_email_sendlog.flash_sync_success', [
                '%updated%' => $result->updated,
                '%checked%' => $result->checked,
            ]));
        }
        if (!$result->available) {
            $this->addFlash('warning', $this->translator->trans('admin_email_sendlog.flash_provider_not_configured'));
        }

        return $this->redirectToRoute('app_admin_email_sendlog');
    }

    /**
     * @return list<AdminTopInfoHtml>
     */
    private function buildInfo(
        int $totalCount,
        int $rangeCount,
        int $problemCount,
        ?DateTimeImmutable $since,
        ?EmailType $template,
        ?string $recipient,
    ): array {
        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $totalCount,
                $this->translator->trans('admin_email_sendlog.summary_total'),
            )),
        ];

        $hasFilter = $since !== null || $template !== null || $recipient !== null;
        if ($hasFilter) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<strong>%d</strong>&nbsp;%s',
                $rangeCount,
                $this->translator->trans('admin_logs.summary_in_range'),
            ));
        }

        if ($problemCount > 0) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-danger is-medium">%d&nbsp;%s</span>',
                $problemCount,
                $this->translator->trans('admin_email_sendlog.summary_failed'),
            ));
        } else {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="tag is-success is-medium">%s</span>',
                $this->translator->trans('admin_email_sendlog.summary_all_delivered'),
            ));
        }

        return $info;
    }

    private function buildTemplateDropdown(
        ?EmailType $current,
        string $range,
        ?string $recipient,
        ?DateTimeImmutable $since,
    ): AdminTopActionDropdown {
        $options = [
            new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_email_sendlog.template_filter_all'),
                target: $this->generateUrl('app_admin_email_sendlog', $this->preserveParams($range, null, $recipient)),
                isActive: $current === null,
            ),
        ];
        foreach (EmailType::cases() as $type) {
            $options[] = new AdminTopActionDropdownOption(
                label: $this->humanizeTemplate($type->value),
                target: $this->generateUrl('app_admin_email_sendlog', $this->preserveParams($range, $type->value, $recipient)),
                isActive: $current === $type,
                count: $this->emailQueueRepo->countFiltered($since, $type, $recipient),
            );
        }

        $label = $current === null
            ? $this->translator->trans('admin_email_sendlog.template_filter_all')
            : $this->humanizeTemplate($current->value);

        return new AdminTopActionDropdown(
            label: sprintf(
                '%s %s',
                $this->translator->trans('admin_email_sendlog.template_filter_label'),
                $label,
            ),
            options: $options,
            icon: 'envelope',
        );
    }

    private function buildRangeDropdown(string $current, ?EmailType $template, ?string $recipient): AdminTopActionDropdown
    {
        $options = [];
        foreach (self::RANGE_OFFSETS as $key => $offset) {
            $optionSince = $offset !== null ? new DateTimeImmutable($offset) : null;
            $options[] = new AdminTopActionDropdownOption(
                label: $this->translator->trans('admin_logs.range_' . $key),
                target: $this->generateUrl('app_admin_email_sendlog', $this->preserveParams($key, $template?->value, $recipient)),
                isActive: $key === $current,
                count: $this->emailQueueRepo->countFiltered($optionSince, $template, $recipient),
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
     * @return array<string, string>
     */
    private function preserveParams(string $range, ?string $template, ?string $recipient): array
    {
        $params = [];
        if ($range !== self::DEFAULT_RANGE) {
            $params['range'] = $range;
        }
        if ($template !== null && $template !== '') {
            $params['template'] = $template;
        }
        if ($recipient !== null && $recipient !== '') {
            $params['recipient'] = $recipient;
        }

        return $params;
    }

    private function humanizeTemplate(string $value): string
    {
        return ucwords(str_replace('_', ' ', $value));
    }

    /**
     * @return list<AdminTopInfoHtml>
     */
    private function buildShowInfo(EmailQueue $email): array
    {
        $statusValue = $email->getStatus()->value;
        $statusVariant = match ($email->getStatus()) {
            EmailQueueStatus::Sent => 'is-success',
            EmailQueueStatus::Failed => 'is-danger',
            EmailQueueStatus::Late => 'is-warning',
            EmailQueueStatus::Pending => 'is-light',
        };

        $info = [
            new AdminTopInfoHtml(sprintf(
                '<strong>%s</strong>',
                $email->getCreatedAt()?->format('Y-m-d H:i:s') ?? '',
            )),
            new AdminTopInfoHtml(sprintf(
                '<span class="tag %s is-medium">%s</span>',
                $statusVariant,
                htmlspecialchars($statusValue, ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            )),
        ];
        if ($email->getRecipient() !== null) {
            $info[] = new AdminTopInfoHtml(sprintf(
                '<span class="has-text-grey">%s</span>',
                htmlspecialchars($email->getRecipient(), ENT_QUOTES | ENT_HTML5, 'UTF-8'),
            ));
        }

        return $info;
    }

    /**
     * @return list<AdminTopActionButton>
     */
    private function buildShowActions(EmailQueue $email): array
    {
        $actions = [];

        $isCapBlocked = $email->getStatus() === EmailQueueStatus::Late
            || ($email->getMaxSendBy() !== null
                && $email->getStatus() === EmailQueueStatus::Pending
                && $email->getMaxSendBy() < new DateTimeImmutable());

        if ($isCapBlocked) {
            $actions[] = new AdminTopActionButton(
                label: $this->translator->trans('admin_email_sendlog.button_clear_cap_retry'),
                target: $this->generateUrl('app_admin_email_sendlog_clear_cap', ['id' => $email->getId()]),
                icon: 'redo',
                variant: 'is-warning',
                confirm: $this->translator->trans('admin_email_sendlog.confirm_clear_cap'),
            );
        }

        $actions[] = new AdminTopActionButton(
            label: $this->translator->trans('global.button_back'),
            target: $this->generateUrl('app_admin_email_sendlog'),
            icon: 'arrow-left',
        );

        return $actions;
    }
}
