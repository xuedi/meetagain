<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateTranslation;
use App\Enum\EmailType;
use App\Repository\EmailTemplateRepository;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class EmailTemplateService
{
    private const string TEMPLATE_PATH = '/templates/email/defaults/';
    private const string DEFAULT_LANGUAGE = 'en';

    /**
     * Translated subjects for each email type per language.
     */
    private const array SUBJECTS = [
        'en' => [
            EmailType::VerificationRequest->value => 'Please Confirm your Email',
            EmailType::Welcome->value => 'Welcome!',
            EmailType::PasswordResetRequest->value => 'Password reset request',
            EmailType::NotificationMessage->value => 'You received a message from {{sender}}',
            EmailType::NotificationRsvpAggregated->value => 'People you follow plan to attend an event',
            EmailType::NotificationEventCanceled->value => 'Event canceled: {{eventTitle}}',
            EmailType::Announcement->value => '{{title}}',
        ],
        'de' => [
            EmailType::VerificationRequest->value => 'Bitte bestätige deine E-Mail',
            EmailType::Welcome->value => 'Willkommen!',
            EmailType::PasswordResetRequest->value => 'Passwort zurücksetzen',
            EmailType::NotificationMessage->value => 'Du hast eine Nachricht von {{sender}} erhalten',
            EmailType::NotificationRsvpAggregated->value => 'Personen, denen du folgst, planen eine Veranstaltung zu besuchen',
            EmailType::NotificationEventCanceled->value => 'Veranstaltung abgesagt: {{eventTitle}}',
            EmailType::Announcement->value => '{{title}}',
        ],
        'cn' => [
            EmailType::VerificationRequest->value => '请确认您的邮箱',
            EmailType::Welcome->value => '欢迎！',
            EmailType::PasswordResetRequest->value => '密码重置请求',
            EmailType::NotificationMessage->value => '您收到了来自 {{sender}} 的消息',
            EmailType::NotificationRsvpAggregated->value => '您关注的人计划参加一个活动',
            EmailType::NotificationEventCanceled->value => '活动已取消：{{eventTitle}}',
            EmailType::Announcement->value => '{{title}}',
        ],
    ];

    /**
     * Variables available for each template type.
     */
    private const array VARIABLES = [
        EmailType::VerificationRequest->value => ['username', 'token', 'host', 'url', 'lang'],
        EmailType::Welcome->value => ['host', 'url', 'lang'],
        EmailType::PasswordResetRequest->value => ['username', 'token', 'host', 'lang'],
        EmailType::NotificationMessage->value => ['username', 'sender', 'senderId', 'host', 'lang'],
        EmailType::NotificationRsvpAggregated->value => ['username', 'attendeeNames', 'eventLocation', 'eventDate', 'eventId', 'eventTitle', 'host', 'lang'],
        EmailType::NotificationEventCanceled->value => ['username', 'eventLocation', 'eventDate', 'eventId', 'eventTitle', 'host', 'lang'],
        EmailType::Announcement->value => ['title', 'content', 'announcementUrl', 'username', 'host', 'lang'],
    ];

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

    /**
     * Get template content for a specific language with fallback to English.
     *
     * @return array{subject: string, body: string}
     */
    public function getTemplateContent(EmailType $identifier, string $language): array
    {
        $template = $this->repo->findByIdentifier($identifier->value);
        if (!$template instanceof EmailTemplate) {
            throw new RuntimeException(sprintf('Email template "%s" not found in database.', $identifier->value));
        }

        // Try requested language first, fallback to English
        $translation = $template->findTranslation($language)
            ?? $template->findTranslation(self::DEFAULT_LANGUAGE);

        if (!$translation instanceof EmailTemplateTranslation) {
            throw new RuntimeException(sprintf('No translation found for email template "%s".', $identifier->value));
        }

        return [
            'subject' => $translation->getSubject() ?? '',
            'body' => $translation->getBody() ?? '',
        ];
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
     * Get default templates for a specific language.
     *
     * @return array<string, array{subject: string, body: string, variables: string[]}>
     */
    public function getDefaultTemplates(string $language = self::DEFAULT_LANGUAGE): array
    {
        $subjects = self::SUBJECTS[$language] ?? self::SUBJECTS[self::DEFAULT_LANGUAGE];

        return [
            EmailType::VerificationRequest->value => [
                'subject' => $subjects[EmailType::VerificationRequest->value],
                'body' => $this->loadTemplateBody(EmailType::VerificationRequest, $language),
                'variables' => self::VARIABLES[EmailType::VerificationRequest->value],
            ],
            EmailType::Welcome->value => [
                'subject' => $subjects[EmailType::Welcome->value],
                'body' => $this->loadTemplateBody(EmailType::Welcome, $language),
                'variables' => self::VARIABLES[EmailType::Welcome->value],
            ],
            EmailType::PasswordResetRequest->value => [
                'subject' => $subjects[EmailType::PasswordResetRequest->value],
                'body' => $this->loadTemplateBody(EmailType::PasswordResetRequest, $language),
                'variables' => self::VARIABLES[EmailType::PasswordResetRequest->value],
            ],
            EmailType::NotificationMessage->value => [
                'subject' => $subjects[EmailType::NotificationMessage->value],
                'body' => $this->loadTemplateBody(EmailType::NotificationMessage, $language),
                'variables' => self::VARIABLES[EmailType::NotificationMessage->value],
            ],
            EmailType::NotificationRsvpAggregated->value => [
                'subject' => $subjects[EmailType::NotificationRsvpAggregated->value],
                'body' => $this->loadTemplateBody(EmailType::NotificationRsvpAggregated, $language),
                'variables' => self::VARIABLES[EmailType::NotificationRsvpAggregated->value],
            ],
            EmailType::NotificationEventCanceled->value => [
                'subject' => $subjects[EmailType::NotificationEventCanceled->value],
                'body' => $this->loadTemplateBody(EmailType::NotificationEventCanceled, $language),
                'variables' => self::VARIABLES[EmailType::NotificationEventCanceled->value],
            ],
            EmailType::Announcement->value => [
                'subject' => $subjects[EmailType::Announcement->value],
                'body' => $this->loadTemplateBody(EmailType::Announcement, $language),
                'variables' => self::VARIABLES[EmailType::Announcement->value],
            ],
        ];
    }

    /**
     * Load template body for a specific language, falling back to default if not found.
     */
    private function loadTemplateBody(EmailType $type, string $language = self::DEFAULT_LANGUAGE): string
    {
        // Try language-specific file first
        $langPath = $this->projectDir . self::TEMPLATE_PATH . $language . '/' . $type->value . '.html';
        if (file_exists($langPath)) {
            return file_get_contents($langPath);
        }

        // Fall back to default (English) template
        $defaultPath = $this->projectDir . self::TEMPLATE_PATH . $type->value . '.html';
        if (file_exists($defaultPath)) {
            return file_get_contents($defaultPath);
        }

        throw new RuntimeException(sprintf('Email template file not found: %s', $defaultPath));
    }
}
