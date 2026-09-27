<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\Guard\Rule\OutboundMailerNotBlocklistedRule;
use App\Emails\MockSampleFactory;
use App\Entity\ItemReport;
use App\Enum\EmailType;
use App\Enum\ItemReportReason;
use App\Service\Config\ConfigService;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ItemReportReceiptEmail extends EmailAbstract
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
        return EmailType::ItemReportReceipt->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_item_report_receipt';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => 'We received your report',
            'context' => [
                'name' => $sample->recipientName,
                'itemLabel' => $sample->eventTitle,
                'itemPath' => '/' . $locale . '/karaoke/1',
                'reason' => $this->translator->trans(ItemReportReason::Copyright->label(), [], null, $locale),
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

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to($report->getNotifierEmail());
        $email->locale($locale);
        $email->context([
            'name' => $report->getNotifierName(),
            'itemLabel' => $report->getItemLabel(),
            'itemPath' => (string) ($context['itemPath'] ?? ''),
            'reason' => $this->translator->trans($report->getReason()->label(), [], null, $locale),
            'reportId' => $report->getId(),
            'lang' => $locale,
        ]);

        return [$email];
    }
}
