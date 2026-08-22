<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\EventInContextRule;
use App\Emails\Guard\Rule\NotificationToggleEnabledRule;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use App\Emails\Guard\Rule\UserNotificationsMasterToggleRule;
use App\Emails\MockSampleFactory;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateInterval;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class EventUpdateNotificationEmail extends EmailAbstract
{
    public function __construct(
        BlocklistCheckerInterface $blocklist,
        MockSampleFactory $samples,
        private EmailQueueInterface $queue,
        private ConfigService $config,
        private TranslatorInterface $translator,
    ) {
        parent::__construct($blocklist, $samples);
    }

    public function getIdentifier(): string
    {
        return EmailType::EventUpdateNotification->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_event_update_notification';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => sprintf('Update to event: %s', $sample->eventTitle),
            'context' => [
                'username' => $sample->recipientName,
                'eventId' => $sample->eventId,
                'eventTitle' => $sample->eventTitle,
                'host' => $sample->host,
                'lang' => $locale,
                'changesHtml' => $this->samples->changesHtml($sample),
            ],
        ];
    }

    public function getGuardRules(): array
    {
        return [
            new RecipientUserPresentRule(),
            new EventInContextRule(),
            new UserNotificationsMasterToggleRule(),
            new NotificationToggleEnabledRule('attendedEventUpdate'),
            new RecipientNotBlocklistedRule($this->blocklist),
        ];
    }

    public function getOrigin(array $context): ?object
    {
        $event = $context['event'] ?? null;

        return $event instanceof Event ? $event : null;
    }

    public function send(array $context): void
    {
        /** @var User $user */
        $user = $context['user'];
        /** @var Event $event */
        $event = $context['event'];
        /** @var array{start: int, startFormatted: string, locationId: ?int, locationName: string, canceled: bool} $before */
        $before = $context['before'];
        /** @var array{start: int, startFormatted: string, locationId: ?int, locationName: string, canceled: bool} $after */
        $after = $context['after'];

        $language = $user->getLocale();
        $changesHtml = $this->renderChangesHtml($before, $after, $language);
        if ($changesHtml === '') {
            return;
        }

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($language);
        $email->context([
            'username' => $user->getName(),
            'eventId' => $event->getId(),
            'eventTitle' => $event->getTitle($language),
            'lang' => $language,
            'changesHtml' => $changesHtml,
        ]);

        $this->queue->enqueue($this, $email, $context);
    }

    public function getMaxSendBy(array $context, DateTimeImmutable $now): ?DateTimeImmutable
    {
        $event = $context['event'] ?? null;
        if (!$event instanceof Event) {
            return null;
        }

        $budgetCutoff = $now->add(new DateInterval('PT6H'));
        $eventStart = DateTimeImmutable::createFromMutable($event->getStart());

        return $budgetCutoff < $eventStart ? $budgetCutoff : $eventStart;
    }

    /**
     * @param array{start: int, startFormatted: string, locationId: ?int, locationName: string, canceled: bool} $before
     * @param array{start: int, startFormatted: string, locationId: ?int, locationName: string, canceled: bool} $after
     */
    private function renderChangesHtml(array $before, array $after, string $language): string
    {
        $lines = [];
        if ($before['start'] !== $after['start']) {
            $lines[] = $this->translator->trans(
                'email_event_update.line_start',
                [
                    '%before%' => $before['startFormatted'],
                    '%after%' => $after['startFormatted'],
                ],
                'messages',
                $language,
            );
        }
        if ($before['locationId'] !== $after['locationId']) {
            $lines[] = $this->translator->trans(
                'email_event_update.line_location',
                [
                    '%before%' => htmlspecialchars($before['locationName']),
                    '%after%' => htmlspecialchars($after['locationName']),
                ],
                'messages',
                $language,
            );
        }
        if ($before['canceled'] !== $after['canceled']) {
            $lines[] = $this->translator->trans(
                $after['canceled'] ? 'email_event_update.line_canceled' : 'email_event_update.line_uncanceled',
                [],
                'messages',
                $language,
            );
        }

        if ($lines === []) {
            return '';
        }

        return '<ul><li>' . implode('</li><li>', $lines) . '</li></ul>';
    }
}
