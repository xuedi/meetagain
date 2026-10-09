<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\Guard\Rule\OutboundMailerNotBlocklistedRule;
use App\Emails\MockSampleFactory;
use App\Entity\ModerationReport;
use App\Entity\User;
use App\Enum\EmailType;
use App\Enum\ModerationReportReason;
use App\Service\Config\ConfigService;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class ModerationWarningEmail extends EmailAbstract
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
        return EmailType::ModerationWarning->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_moderation_warning';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => 'A note from the moderators',
            'context' => [
                'name' => $sample->recipientName,
                'reason' => $this->translator->trans(ModerationReportReason::Harassment->label(), [], null, $locale),
                'note' => $sample->responseText,
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
        /** @var ModerationReport $report */
        $report = $context['report'];
        $author = $report->getAuthor();
        if (!$author instanceof User) {
            return [];
        }

        $locale = $author->getLocale();

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $author->getEmail());
        $email->locale($locale);
        $email->context([
            'name' => $author->getName(),
            'reason' => $this->translator->trans($report->getReason()->label(), [], null, $locale),
            'note' => (string) $context['note'],
            'lang' => $locale,
        ]);

        return [$email];
    }
}
