<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\Guard\Rule\OutboundMailerNotBlocklistedRule;
use App\Emails\MockSampleFactory;
use App\Entity\ItemReport;
use App\Enum\EmailType;
use App\Enum\ItemReportStatus;
use App\Service\Config\ConfigService;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ItemReportDecisionEmail extends EmailAbstract
{
    public function __construct(
        BlocklistInterface $blocklist,
        MockSampleFactory $samples,
        MailerInterface $mailer,
        private ConfigService $config,
        private TranslatorInterface $translator,
    ) {
        parent::__construct($blocklist, $samples, $mailer);
    }

    public function getIdentifier(): string
    {
        return EmailType::ItemReportDecision->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_item_report_decision';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => 'A decision on your report',
            'context' => [
                'name' => $sample->recipientName,
                'itemLabel' => $sample->eventTitle,
                'decision' => $this->translator->trans('item_report.decision_removed', [], null, $locale),
                'reportId' => 42,
                'host' => $sample->host,
                'url' => $sample->url,
                'lang' => $locale,
            ],
        ];
    }

    public function getGuardRules(): array
    {
        return [
            new OutboundMailerNotBlocklistedRule($this->blocklist, $this->config),
        ];
    }

    public function compose(array $context): array
    {
        /** @var ItemReport $report */
        $report = $context['report'];
        $locale = $report->getLocale();

        $decisionKey = $report->getStatus() === ItemReportStatus::Removed ? 'item_report.decision_removed' : 'item_report.decision_kept';

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to($report->getNotifierEmail());
        $email->locale($locale);
        $email->context([
            'name' => $report->getNotifierName(),
            'itemLabel' => $report->getItemLabel(),
            'decision' => $this->translator->trans($decisionKey, [], null, $locale),
            'reportId' => $report->getId(),
            'lang' => $locale,
        ]);

        return [$email];
    }
}
