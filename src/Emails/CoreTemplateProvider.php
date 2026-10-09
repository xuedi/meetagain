<?php declare(strict_types=1);

namespace App\Emails;

use App\Enum\EmailType;
use App\ExtendedFilesystem;
use Module\Email\Contract\TemplateDefinition;
use Module\Email\Contract\TemplateProviderInterface;
use RuntimeException;
use Symfony\Component\DependencyInjection\Attribute\AsTaggedItem;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

#[AsTaggedItem(priority: 100)]
readonly class CoreTemplateProvider implements TemplateProviderInterface
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
            EmailType::SupportEmailVerify->value => 'Confirm your email address',
            EmailType::SupportInvitation->value => '{{invitedBy}} asked you to join a support request',
            EmailType::AdminNotification->value => 'Admin: Items require your attention',
            EmailType::EventReminder->value => 'Reminder: {{eventTitle}} is today',
            EmailType::UpcomingEvents->value => 'Upcoming events this week',
            EmailType::EventUpdateNotification->value => 'Update to event: {{eventTitle}}',
            EmailType::SeriesRescheduled->value => 'Series rescheduled: {{eventTitle}}',
            EmailType::ItemReportReceipt->value => 'We received your report about {{itemLabel}}',
            EmailType::ItemReportDecision->value => 'A decision on your report about {{itemLabel}}',
            EmailType::ModerationWarning->value => 'A note from the moderators of {{url}}',
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
            EmailType::SupportEmailVerify->value => 'Bestätige deine E-Mail-Adresse',
            EmailType::SupportInvitation->value => '{{invitedBy}} bittet dich um Hilfe bei einer Supportanfrage',
            EmailType::AdminNotification->value => 'Admin: Es gibt Punkte, die deine Aufmerksamkeit erfordern',
            EmailType::EventReminder->value => 'Erinnerung: {{eventTitle}} ist heute',
            EmailType::UpcomingEvents->value => 'Deine Veranstaltungen diese Woche',
            EmailType::EventUpdateNotification->value => 'Änderung an Veranstaltung: {{eventTitle}}',
            EmailType::SeriesRescheduled->value => 'Terminserie verschoben: {{eventTitle}}',
            EmailType::ItemReportReceipt->value => 'Wir haben deine Meldung zu {{itemLabel}} erhalten',
            EmailType::ItemReportDecision->value => 'Entscheidung zu deiner Meldung über {{itemLabel}}',
            EmailType::ModerationWarning->value => 'Ein Hinweis der Moderation von {{url}}',
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
            EmailType::SupportEmailVerify->value => '请确认您的邮箱地址',
            EmailType::SupportInvitation->value => '{{invitedBy}} 邀请您协助处理一条支持请求',
            EmailType::AdminNotification->value => '管理员：有事项需要您处理',
            EmailType::EventReminder->value => '提醒：{{eventTitle}} 就在今天',
            EmailType::UpcomingEvents->value => '本周即将举行的活动',
            EmailType::EventUpdateNotification->value => '活动有变更：{{eventTitle}}',
            EmailType::SeriesRescheduled->value => '系列活动时间调整：{{eventTitle}}',
            EmailType::ItemReportReceipt->value => '我们已收到你对 {{itemLabel}} 的举报',
            EmailType::ItemReportDecision->value => '关于你对 {{itemLabel}} 的举报的处理结果',
            EmailType::ModerationWarning->value => '来自 {{url}} 管理团队的提醒',
        ],
        'fr' => [
            EmailType::VerificationRequest->value => 'Merci de confirmer ton adresse e-mail',
            EmailType::Welcome->value => 'Bienvenue !',
            EmailType::PasswordResetRequest->value => 'Demande de réinitialisation de mot de passe',
            EmailType::NotificationMessage->value => 'Tu as reçu un message de {{sender}}',
            EmailType::NotificationRsvpAggregated->value => 'Des personnes que tu suis prévoient de participer à un événement',
            EmailType::NotificationEventCanceled->value => 'Événement annulé : {{eventTitle}}',
            EmailType::Announcement->value => '{{title}}',
            EmailType::SupportNotification->value => 'Nouvelle demande de support de {{name}}',
            EmailType::SupportResponse->value => 'Re : ta demande de support',
            EmailType::SupportEmailVerify->value => 'Confirme ton adresse e-mail',
            EmailType::SupportInvitation->value => '{{invitedBy}} te demande de rejoindre une demande de support',
            EmailType::AdminNotification->value => 'Admin : des éléments nécessitent ton attention',
            EmailType::EventReminder->value => 'Rappel : {{eventTitle}} a lieu aujourd\'hui',
            EmailType::UpcomingEvents->value => 'Événements à venir cette semaine',
            EmailType::EventUpdateNotification->value => 'Événement modifié : {{eventTitle}}',
            EmailType::SeriesRescheduled->value => 'Série reportée : {{eventTitle}}',
            EmailType::ItemReportReceipt->value => 'Nous avons reçu ton signalement concernant {{itemLabel}}',
            EmailType::ItemReportDecision->value => 'Décision sur ton signalement concernant {{itemLabel}}',
            EmailType::ModerationWarning->value => 'Un message de la modération de {{url}}',
        ],
        'es' => [
            EmailType::VerificationRequest->value => 'Confirma tu correo electrónico',
            EmailType::Welcome->value => '¡Te damos la bienvenida!',
            EmailType::PasswordResetRequest->value => 'Solicitud de restablecimiento de contraseña',
            EmailType::NotificationMessage->value => 'Has recibido un mensaje de {{sender}}',
            EmailType::NotificationRsvpAggregated->value => 'Personas que sigues planean asistir a un evento',
            EmailType::NotificationEventCanceled->value => 'Evento cancelado: {{eventTitle}}',
            EmailType::Announcement->value => '{{title}}',
            EmailType::SupportNotification->value => 'Nueva solicitud de soporte de {{name}}',
            EmailType::SupportResponse->value => 'Re: tu solicitud de soporte',
            EmailType::SupportEmailVerify->value => 'Confirma tu dirección de correo',
            EmailType::SupportInvitation->value => '{{invitedBy}} te pide ayuda con una solicitud de soporte',
            EmailType::AdminNotification->value => 'Admin: hay elementos que requieren tu atención',
            EmailType::EventReminder->value => 'Recordatorio: {{eventTitle}} es hoy',
            EmailType::UpcomingEvents->value => 'Eventos próximos esta semana',
            EmailType::EventUpdateNotification->value => 'Cambio en el evento: {{eventTitle}}',
            EmailType::SeriesRescheduled->value => 'Serie reprogramada: {{eventTitle}}',
            EmailType::ItemReportReceipt->value => 'Hemos recibido tu denuncia sobre {{itemLabel}}',
            EmailType::ItemReportDecision->value => 'Decisión sobre tu denuncia de {{itemLabel}}',
            EmailType::ModerationWarning->value => 'Un aviso de la moderación de {{url}}',
        ],
    ];

    private const array HTML_VARIABLES = ['content', 'sections', 'eventsHtml', 'changesHtml', 'removedDatesHtml'];

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
            'audience',
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
        EmailType::SupportEmailVerify->value => [
            'host',
            'url',
            'lang',
            'token',
            'expiresAt',
            'greeting',
        ],
        EmailType::SupportInvitation->value => [
            'invitedBy',
            'name',
            'message',
            'createdAt',
            'requestId',
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
        EmailType::ItemReportReceipt->value => [
            'name',
            'itemLabel',
            'itemPath',
            'reason',
            'reportId',
            'host',
            'url',
            'lang',
            'greeting',
        ],
        EmailType::ItemReportDecision->value => [
            'name',
            'itemLabel',
            'decision',
            'reportId',
            'host',
            'url',
            'lang',
            'greeting',
        ],
        EmailType::ModerationWarning->value => [
            'name',
            'reason',
            'note',
            'host',
            'url',
            'lang',
            'greeting',
        ],
    ];

    public function __construct(
        private ExtendedFilesystem $fs,
        #[Autowire('%kernel.project_dir%')]
        private string $projectDir,
    ) {}

    public function getDefinitions(string $language): array
    {
        $subjects = self::SUBJECTS[$language] ?? self::SUBJECTS[self::DEFAULT_LANGUAGE];

        $definitions = [];
        foreach (self::VARIABLES as $identifier => $variables) {
            $definitions[] = new TemplateDefinition(
                identifier: $identifier,
                subject: $subjects[$identifier],
                body: $this->loadTemplateBody($identifier, $language),
                variables: $variables,
                htmlVariables: array_values(array_intersect(self::HTML_VARIABLES, $variables)),
            );
        }

        return $definitions;
    }

    private function loadTemplateBody(string $identifier, string $language = self::DEFAULT_LANGUAGE): string
    {
        $langPath = $this->projectDir . self::TEMPLATE_PATH . $language . '/' . $identifier . '.html';
        if ($this->fs->fileExists($langPath)) {
            return $this->fs->getFileContents($langPath) ?: '';
        }

        $defaultPath = $this->projectDir . self::TEMPLATE_PATH . $identifier . '.html';
        if ($this->fs->fileExists($defaultPath)) {
            return $this->fs->getFileContents($defaultPath) ?: '';
        }

        throw new RuntimeException(sprintf('Email template file not found: %s', $defaultPath));
    }
}
