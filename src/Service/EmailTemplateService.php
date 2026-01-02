<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailTemplate;
use App\Enum\EmailType;
use App\Repository\EmailTemplateRepository;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class EmailTemplateService
{
    private const string TEMPLATE_PATH = '/templates/email/defaults/';

    public function __construct(
        private EmailTemplateRepository $repo,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {
    }

    public function getTemplate(EmailType $identifier): ?EmailTemplate
    {
        return $this->repo->findByIdentifier($identifier->value);
    }

    public function renderContent(string $content, array $context): string
    {
        foreach ($context as $key => $value) {
            if (is_scalar($value)) {
                $content = str_replace('{{' . $key . '}}', (string) $value, $content);
            }
        }

        return $content;
    }

    /**
     * @return array<string, array{subject: string, body: string, variables: string[]}>
     */
    public function getDefaultTemplates(): array
    {
        return [
            EmailType::VerificationRequest->value => [
                'subject' => 'Please Confirm your Email',
                'body' => $this->loadTemplateBody(EmailType::VerificationRequest),
                'variables' => ['username', 'token', 'host', 'url', 'lang'],
            ],
            EmailType::Welcome->value => [
                'subject' => 'Welcome!',
                'body' => $this->loadTemplateBody(EmailType::Welcome),
                'variables' => ['host', 'url', 'lang'],
            ],
            EmailType::PasswordResetRequest->value => [
                'subject' => 'Password reset request',
                'body' => $this->loadTemplateBody(EmailType::PasswordResetRequest),
                'variables' => ['username', 'token', 'host', 'lang'],
            ],
            EmailType::NotificationMessage->value => [
                'subject' => 'You received a message from {{sender}}',
                'body' => $this->loadTemplateBody(EmailType::NotificationMessage),
                'variables' => ['username', 'sender', 'senderId', 'host', 'lang'],
            ],
            EmailType::NotificationRsvpAggregated->value => [
                'subject' => 'People you follow plan to attend an event',
                'body' => $this->loadTemplateBody(EmailType::NotificationRsvpAggregated),
                'variables' => ['username', 'attendeeNames', 'eventLocation', 'eventDate', 'eventId', 'eventTitle', 'host', 'lang'],
            ],
            EmailType::NotificationEventCanceled->value => [
                'subject' => 'Event canceled: {{eventTitle}}',
                'body' => $this->loadTemplateBody(EmailType::NotificationEventCanceled),
                'variables' => ['username', 'eventLocation', 'eventDate', 'eventId', 'eventTitle', 'host', 'lang'],
            ],
            EmailType::Announcement->value => [
                'subject' => '{{title}}',
                'body' => $this->loadTemplateBody(EmailType::Announcement),
                'variables' => ['announcement', 'announcementUrl', 'username', 'host', 'lang'],
            ],
        ];
    }

    private function loadTemplateBody(EmailType $type): string
    {
        $filePath = $this->projectDir . self::TEMPLATE_PATH . $type->value . '.html';

        if (!file_exists($filePath)) {
            throw new RuntimeException(sprintf('Email template file not found: %s', $filePath));
        }

        return file_get_contents($filePath);
    }
}
