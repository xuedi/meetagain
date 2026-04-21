<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailInterface;
use App\Emails\EmailQueueInterface;
use App\Entity\SupportRequest;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class SupportNotificationEmail implements EmailInterface
{
    public function __construct(
        private EmailQueueInterface $queue,
        private ConfigService $config,
    ) {}

    public function getIdentifier(): string
    {
        return EmailType::SupportNotification->value;
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

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        return null;
    }
}
