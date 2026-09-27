<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\Guard\Rule\OutboundMailerNotBlocklistedRule;
use App\Emails\Guard\Rule\SupportRequestPresentRule;
use App\Emails\MockSampleFactory;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Support\RecipientResolver;
use Module\Email\Contract\BlocklistInterface;
use Module\Email\Contract\MailerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class SupportInvitationEmail extends EmailAbstract
{
    public function __construct(
        BlocklistInterface $blocklist,
        MockSampleFactory $samples,
        MailerInterface $mailer,
        private ConfigService $config,
        private RecipientResolver $recipientResolver,
        private LoggerInterface $logger,
    ) {
        parent::__construct($blocklist, $samples, $mailer);
    }

    public function getIdentifier(): string
    {
        return EmailType::SupportInvitation->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_support_invitation';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => sprintf('%s asked you to join a support request', $sample->adminName),
            'context' => [
                'invitedBy' => $sample->adminName,
                'name' => $sample->recipientName,
                'message' => $sample->messageText,
                'createdAt' => $sample->dates->createdAt,
                'requestId' => '318',
                'lang' => $locale,
            ],
        ];
    }

    public function getGuardRules(): array
    {
        return [
            new SupportRequestPresentRule(),
            new OutboundMailerNotBlocklistedRule($this->blocklist, $this->config),
        ];
    }

    public function compose(array $context): array
    {
        /** @var SupportRequest $request */
        $request = $context['request'];

        $admins = $this->recipientResolver->resolveAdmins();
        if ($admins === []) {
            $this->logger->warning('Support request escalated but no admin recipients could be resolved', [
                'support_request_id' => $request->getId(),
            ]);
            return [];
        }

        $invitedBy = $request->getInvitedAdminsBy();

        $emails = [];
        foreach ($admins as $admin) {
            $email = new TemplatedEmail();
            $email->from($this->config->getMailerAddress());
            $email->to((string) $admin->getEmail());
            $email->locale($admin->getLocale());
            $email->context([
                'invitedBy' => $invitedBy instanceof User ? $invitedBy->getName() : '',
                'name' => $request->getRequesterLabel(),
                'message' => $request->getMessage(),
                'createdAt' => $request->getCreatedAt()->format('Y-m-d H:i:s'),
                'requestId' => (string) $request->getId(),
            ]);

            $emails[] = $email;
        }

        return $emails;
    }
}
