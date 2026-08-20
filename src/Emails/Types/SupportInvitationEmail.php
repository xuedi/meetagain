<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\OutboundMailerNotBlocklistedRule;
use App\Emails\Guard\Rule\SupportRequestPresentRule;
use App\Entity\SupportRequest;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use App\Service\Support\RecipientResolver;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class SupportInvitationEmail extends EmailAbstract
{
    public function __construct(
        BlocklistCheckerInterface $blocklist,
        private EmailQueueInterface $queue,
        private ConfigService $config,
        private RecipientResolver $recipientResolver,
        private LoggerInterface $logger,
    ) {
        parent::__construct($blocklist);
    }

    public function getIdentifier(): string
    {
        return EmailType::SupportInvitation->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_support_invitation';
    }

    public function getDisplayMockData(): array
    {
        return [
            'subject' => 'Jane Steward asked you to join a support request',
            'context' => [
                'invitedBy' => 'Jane Steward',
                'name' => 'John Doe',
                'message' => 'I need help with my account.',
                'createdAt' => '2025-01-01 12:00:00',
                'requestId' => '42',
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

    public function send(array $context): void
    {
        /** @var SupportRequest $request */
        $request = $context['request'];

        $admins = $this->recipientResolver->resolveAdmins();
        if ($admins === []) {
            $this->logger->warning('Support request escalated but no admin recipients could be resolved', [
                'support_request_id' => $request->getId(),
            ]);
            return;
        }

        $invitedBy = $request->getInvitedAdminsBy();

        foreach ($admins as $admin) {
            $email = new TemplatedEmail();
            $email->from($this->config->getMailerAddress());
            $email->to((string) $admin->getEmail());
            $email->locale('en');
            $email->context([
                'invitedBy' => $invitedBy instanceof User ? $invitedBy->getName() : '',
                'name' => $request->getRequesterLabel(),
                'message' => $request->getMessage(),
                'createdAt' => $request->getCreatedAt()->format('Y-m-d H:i:s'),
                'requestId' => (string) $request->getId(),
            ]);

            $this->queue->enqueue($this, $email, $context);
        }
    }
}
