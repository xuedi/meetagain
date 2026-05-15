<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\OutboundMailerNotBlocklistedRule;
use App\Emails\Guard\Rule\SupportRequestPresentRule;
use App\Entity\SupportRequest;
use App\Enum\EmailType;
use App\Repository\UserRepository;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class SupportNotificationEmail extends EmailAbstract
{
    public function __construct(
        BlocklistCheckerInterface $blocklist,
        private EmailQueueInterface $queue,
        private ConfigService $config,
        private UserRepository $userRepository,
        private LoggerInterface $logger,
    ) {
        parent::__construct($blocklist);
    }

    public function getIdentifier(): string
    {
        return EmailType::SupportNotification->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_support_notification';
    }

    public function getDisplayMockData(): array
    {
        return [
            'subject' => 'New Support Request from John Doe',
            'context' => [
                'name' => 'John Doe',
                'email' => 'john.doe@example.org',
                'message' => 'I need help with my account.',
                'createdAt' => '2025-01-01 12:00:00',
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

        $admins = $this->userRepository->findAdminUsers();
        if ($admins === []) {
            $this->logger->warning('Support ticket received but no active admin recipients found', [
                'support_request_id' => $request->getId(),
            ]);
            return;
        }

        foreach ($admins as $admin) {
            $email = new TemplatedEmail();
            $email->from($this->config->getMailerAddress());
            $email->to((string) $admin->getEmail());
            $email->locale('en');
            $email->context([
                'contactType' => $request->getContactType()->label(),
                'name' => $request->getName(),
                'email' => $request->getEmail(),
                'message' => $request->getMessage(),
                'createdAt' => $request->getCreatedAt()->format('Y-m-d H:i:s'),
            ]);

            $this->queue->enqueue($this, $email, EmailType::SupportNotification, $context);
        }
    }
}
