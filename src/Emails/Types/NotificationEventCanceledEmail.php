<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\EventInContextRule;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use App\Emails\MockSampleFactory;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EmailType;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateInterval;
use DateTimeImmutable;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class NotificationEventCanceledEmail extends EmailAbstract
{
    public function __construct(
        BlocklistCheckerInterface $blocklist,
        MockSampleFactory $samples,
        private EmailQueueInterface $queue,
        private ConfigService $config,
    ) {
        parent::__construct($blocklist, $samples);
    }

    public function getIdentifier(): string
    {
        return EmailType::NotificationEventCanceled->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_notification_event_canceled';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => sprintf('Event canceled: %s', $sample->eventTitle),
            'context' => [
                'username' => $sample->recipientName,
                'eventLocation' => $sample->eventLocation,
                'eventDate' => $sample->eventDate,
                'eventId' => $sample->eventId,
                'eventTitle' => $sample->eventTitle,
                'host' => $sample->host,
                'lang' => $locale,
            ],
        ];
    }

    public function getGuardRules(): array
    {
        return [
            new RecipientUserPresentRule(),
            new EventInContextRule(),
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

        $language = $user->getLocale();

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $user->getEmail());
        $email->locale($language);
        $email->context([
            'username' => $user->getName(),
            'eventLocation' => $event->getLocation()?->getName() ?? '',
            'eventDate' => $event->getStart()->format('Y-m-d'),
            'eventId' => $event->getId(),
            'eventTitle' => $event->getTitle($language),
            'lang' => $language,
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
}
