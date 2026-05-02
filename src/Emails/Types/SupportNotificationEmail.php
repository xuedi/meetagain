<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Entity\SupportRequest;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class SupportNotificationEmail extends EmailAbstract
{
    public function __construct(
        BlocklistCheckerInterface $blocklist,
        private EmailQueueInterface $queue,
        private ConfigService $config,
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

    public function guardCheck(array $context): bool
    {
        $this->ensureInstanceOf($context, 'request', SupportRequest::class);

        if ($this->isBlocked($this->config->getMailerAddress()->getAddress())) {
            return false;
        }

        return true;
    }

    public function send(array $context): void
    {
        /** @var SupportRequest $request */
        $request = $context['request'];

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to($this->config->getMailerAddress());
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
