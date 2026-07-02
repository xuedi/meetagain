<?php declare(strict_types=1);

namespace App\Service\Email;

use App\Entity\EmailTemplate;
use App\Entity\EmailTemplateTranslation;
use App\Enum\EmailType;
use App\ExtendedFilesystem;
use App\Repository\EmailTemplateRepository;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class EmailTemplateService
{
    private const string TEMPLATE_PATH = '/templates/email/defaults/';
    private const string DEFAULT_LANGUAGE = 'en';

    private const array SUBJECTS = [
        'en' => [
            EmailType::VerificationRequest->value => 'Please Confirm your Email',
            EmailType::Welcome->value => 'Welcome!',
            EmailType::PasswordResetRequest->value => 'Password reset request',
            EmailType::NotificationMessage->value => 'You received a message from {{sender}}',
            EmailType::NotificationRsvpAggregated->value => 'People you follow plan to attend an event',
            EmailType::NotificationEventCanceled->value => 'Event canceled: {{eventTitle}}',
            EmailType::Announcement->value => '{{title}}',
            EmailType::SupportNotification->value => 'New Support Request from {{name}}',
            EmailType::SupportResponse->value => 'Re: your support request',
            EmailType::AdminNotification->value => 'Admin: Items require your attention',
            EmailType::EventReminder->value => 'Reminder: {{eventTitle}} is today',
            EmailType::UpcomingEvents->value => 'Upcoming events this week',
            EmailType::EventUpdateNotification->value => 'Update to event: {{eventTitle}}',
            EmailType::SeriesRescheduled->value => 'Series rescheduled: {{eventTitle}}',
        ],
        'de' => [
            EmailType::VerificationRequest->value => 'Bitte bestätige deine E-Mail',
            EmailType::Welcome->value => 'Willkommen!',
            EmailType::PasswordResetRequest->value => 'Passwort zurücksetzen',
            EmailType::NotificationMessage->value => 'Du hast eine Nachricht von {{sender}} erhalten',
            EmailType::NotificationRsvpAggregated->value => 'Personen, denen du folgst, planen eine Veranstaltung zu besuchen',
            EmailType::NotificationEventCanceled->value => 'Veranstaltung abgesagt: {{eventTitle}}',
            EmailType::Announcement->value => '{{title}}',
            EmailType::SupportNotification->value => 'Neue Supportanfrage von {{name}}',
            EmailType::SupportResponse->value => 'Re: deine Supportanfrage',
            EmailType::AdminNotification->value => 'Admin: Es gibt Punkte, die deine Aufmerksamkeit erfordern',
            EmailType::EventReminder->value => 'Erinnerung: {{eventTitle}} ist heute',
            EmailType::UpcomingEvents->value => 'Deine Veranstaltungen diese Woche',
            EmailType::EventUpdateNotification->value => 'Änderung an Veranstaltung: {{eventTitle}}',
            EmailType::SeriesRescheduled->value => 'Terminserie verschoben: {{eventTitle}}',
        ],
        'zh' => [
            EmailType::VerificationRequest->value => '请确认您的邮箱',
            EmailType::Welcome->value => '欢迎！',
            EmailType::PasswordResetRequest->value => '密码重置请求',
            EmailType::NotificationMessage->value => '您收到了来自 {{sender}} 的消息',
            EmailType::NotificationRsvpAggregated->value => '您关注的人计划参加一个活动',
            EmailType::NotificationEventCanceled->value => '活动已取消：{{eventTitle}}',
            EmailType::Announcement->value => '{{title}}',
            EmailType::SupportNotification->value => '{{name}} 的新支持请求',
            EmailType::SupportResponse->value => '回复：您的支持请求',
            EmailType::AdminNotification->value => '管理员：有事项需要您处理',
            EmailType::EventReminder->value => '提醒：{{eventTitle}} 就在今天',
            EmailType::UpcomingEvents->value => '本周即将举行的活动',
            EmailType::EventUpdateNotification->value => '活动有变更：{{eventTitle}}',
            EmailType::SeriesRescheduled->value => '系列活动时间调整：{{eventTitle}}',
        ],
    ];

    private const array VARIABLES = [
        EmailType::VerificationRequest->value => ['username', 'token', 'host', 'url', 'lang', 'greeting'],
        EmailType::Welcome->value => ['host', 'url', 'lang', 'greeting'],
        EmailType::PasswordResetRequest->value => ['username', 'token', 'host', 'lang', 'greeting'],
        EmailType::NotificationMessage->value => ['username', 'sender', 'senderId', 'host', 'lang', 'greeting'],
        EmailType::NotificationRsvpAggregated->value => [
            'username',
            'attendeeNames',
            'eventLocation',
            'eventDate',
            'eventId',
            'eventTitle',
            'host',
            'lang',
            'greeting',
        ],
        EmailType::NotificationEventCanceled->value => [
            'username',
            'eventLocation',
            'eventDate',
            'eventId',
            'eventTitle',
            'host',
            'lang',
            'greeting',
        ],
        EmailType::Announcement->value => [
            'title',
            'content',
            'announcementUrl',
            'username',
            'host',
            'lang',
            'greeting',
        ],
        EmailType::SupportNotification->value => [
            'name',
            'email',
            'message',
            'createdAt',
            'greeting',
        ],
        EmailType::SupportResponse->value => [
            'name',
            'originalMessage',
            'response',
            'createdAt',
            'greeting',
        ],
        EmailType::AdminNotification->value => [
            'username',
            'sections',
            'host',
            'lang',
            'greeting',
        ],
        EmailType::EventReminder->value => [
            'username',
            'eventTitle',
            'eventLocation',
            'eventDate',
            'eventTime',
            'eventId',
            'host',
            'lang',
            'greeting',
        ],
        EmailType::UpcomingEvents->value => [
            'username',
            'eventsHtml',
            'host',
            'lang',
            'greeting',
        ],
        EmailType::EventUpdateNotification->value => [
            'username',
            'eventId',
            'eventTitle',
            'changesHtml',
            'host',
            'lang',
            'greeting',
        ],
        EmailType::SeriesRescheduled->value => [
            'username',
            'eventTitle',
            'eventId',
            'host',
            'lang',
            'greeting',
            'removedDatesHtml',
            'newStart',
        ],
    ];

    public function __construct(
        private EmailTemplateRepository $repo,
        private ExtendedFilesystem $fs,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {}

    public function getTemplate(EmailType $identifier): ?EmailTemplate
    {
        return $this->repo->findByIdentifier($identifier->value);
    }

    /**
     * @return array{subject: string, body: string}
     */
    public function getTemplateContent(EmailType $identifier, string $language): array
    {
        $template = $this->repo->findByIdentifier($identifier->value);
        if (!$template instanceof EmailTemplate) {
            throw new RuntimeException(sprintf('Email template "%s" not found in database.', $identifier->value));
        }

        // Try requested language first, fallback to English
        $translation = $template->findTranslation($language) ?? $template->findTranslation(self::DEFAULT_LANGUAGE);

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
            if (!is_scalar($value)) {
                continue;
            }

            $content = str_replace('{{' . $key . '}}', (string) $value, $content);
        }

        return $content;
    }

    /**
     * @return array<string, array{subject: string, body: string, variables: string[]}>
     */
    public function getDefaultTemplates(string $language = self::DEFAULT_LANGUAGE): array
    {
        $subjects = self::SUBJECTS[$language] ?? self::SUBJECTS[self::DEFAULT_LANGUAGE];

        $templates = [];
        foreach (self::VARIABLES as $type => $variables) {
            $templates[$type] = [
                'subject' => $subjects[$type],
                'body' => $this->loadTemplateBody(EmailType::from($type), $language),
                'variables' => $variables,
            ];
        }

        return $templates;
    }

    private function loadTemplateBody(EmailType $type, string $language = self::DEFAULT_LANGUAGE): string
    {
        $langPath = $this->projectDir . self::TEMPLATE_PATH . $language . '/' . $type->value . '.html';
        if ($this->fs->fileExists($langPath)) {
            return $this->fs->getFileContents($langPath) ?: '';
        }

        $defaultPath = $this->projectDir . self::TEMPLATE_PATH . $type->value . '.html';
        if ($this->fs->fileExists($defaultPath)) {
            return $this->fs->getFileContents($defaultPath) ?: '';
        }

        throw new RuntimeException(sprintf('Email template file not found: %s', $defaultPath));
    }
}
