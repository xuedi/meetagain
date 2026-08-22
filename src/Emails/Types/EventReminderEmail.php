<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\DueContext;
use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\EventInContextRule;
use App\Emails\Guard\Rule\NotificationToggleEnabledRule;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use App\Emails\Guard\Rule\UserNotificationsMasterToggleRule;
use App\Emails\MockSampleFactory;
use App\Emails\ScheduledEmailInterface;
use App\Emails\ScheduledMailItem;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EmailType;
use App\Repository\EventRepository;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class EventReminderEmail extends EmailAbstract implements ScheduledEmailInterface
{
    public function __construct(
        BlocklistCheckerInterface $blocklist,
        MockSampleFactory $samples,
        private EmailQueueInterface $queue,
        private ConfigService $config,
        private EventRepository $eventRepo,
        private EntityManagerInterface $em,
    ) {
        parent::__construct($blocklist, $samples);
    }

    public function getIdentifier(): string
    {
        return EmailType::EventReminder->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_event_reminder';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => sprintf('Reminder: %s is today', $sample->eventTitle),
            'context' => [
                'username' => $sample->recipientName,
                'eventTitle' => $sample->eventTitle,
                'eventLocation' => $sample->eventLocation,
                'eventDate' => $sample->eventDate,
                'eventTime' => $sample->eventTime,
                'eventId' => $sample->eventId,
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
            new UserNotificationsMasterToggleRule(),
            new NotificationToggleEnabledRule('eventReminder'),
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
            'eventTitle' => $event->getTitle($language),
            'eventLocation' => $event->getLocation()?->getName() ?? '',
            'eventDate' => $event->getStart()->format('Y-m-d'),
            'eventTime' => $event->getStart()->format('H:i'),
            'eventId' => $event->getId(),
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

        $budgetCutoff = $now->add(new DateInterval('PT3H'));
        $eventStart = DateTimeImmutable::createFromMutable($event->getStart());

        return $budgetCutoff < $eventStart ? $budgetCutoff : $eventStart;
    }

    public function getDueContexts(DateTimeImmutable $now): array
    {
        $events = $this->eventRepo->findEventsNeedingReminder($now, $now->add(new DateInterval('PT5H')));

        $contexts = [];
        foreach ($events as $event) {
            $contexts[] = new DueContext(['event' => $event], $event->getRsvp()->toArray());
        }

        return $contexts;
    }

    public function getPreviewContexts(DateTimeImmutable $for): array
    {
        return $this->getDueContexts($for);
    }

    public function markContextSent(DueContext $context): void
    {
        /** @var Event $event */
        $event = $context->data['event'];
        $event->setEventReminderSentAt(new DateTimeImmutable());
        $this->em->flush();
    }

    public function getPlannedItems(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $events = $this->eventRepo->findEventsNeedingReminder($from->add(new DateInterval('PT5H')), $to->add(new DateInterval('PT5H')));

        $items = [];
        foreach ($events as $event) {
            $eligibleCount = 0;
            foreach ($event->getRsvp()->toArray() as $user) {
                try {
                    $eligible = $this->guardCheck(['user' => $user, 'event' => $event]);
                } catch (InvalidArgumentException) {
                    continue;
                }
                if ($eligible) {
                    $eligibleCount++;
                }
            }

            $items[] = new ScheduledMailItem(
                mailType: EmailType::EventReminder->value,
                label: 'Event: ' . ($event->getTitle('en') ?: ($event->getTranslation()->first() ?: null)?->getTitle() ?? ''),
                expectedTime: DateTimeImmutable::createFromMutable($event->getStart())->sub(new DateInterval('PT5H')),
                expectedRecipients: $eligibleCount,
            );
        }

        return $items;
    }
}
