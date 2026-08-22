<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\DueContext;
use App\Emails\EmailAbstract;
use App\Emails\EmailQueueInterface;
use App\Emails\Guard\Rule\EventInContextRule;
use App\Emails\Guard\Rule\NotificationToggleEnabledRule;
use App\Emails\Guard\Rule\RecipientNotAlreadyRsvpdRule;
use App\Emails\Guard\Rule\RecipientNotBlocklistedRule;
use App\Emails\Guard\Rule\RecipientUserPresentRule;
use App\Emails\Guard\Rule\RsvpAttendeeMapPresentRule;
use App\Emails\Guard\Rule\UserNotificationsMasterToggleRule;
use App\Emails\MockSampleFactory;
use App\Emails\ScheduledEmailInterface;
use App\Emails\ScheduledMailItem;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EmailType;
use App\Filter\Event\FollowerEventNotificationFilterInterface;
use App\Repository\EventRepository;
use App\Service\Config\ConfigService;
use App\Service\Email\BlocklistCheckerInterface;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use InvalidArgumentException;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class RsvpAggregatedEmail extends EmailAbstract implements ScheduledEmailInterface
{
    public function __construct(
        BlocklistCheckerInterface $blocklist,
        MockSampleFactory $samples,
        private EmailQueueInterface $queue,
        private ConfigService $config,
        private EventRepository $eventRepo,
        private EntityManagerInterface $em,
        #[AutowireIterator(FollowerEventNotificationFilterInterface::class)]
        private iterable $followerFilters = [],
    ) {
        parent::__construct($blocklist, $samples);
    }

    public function getIdentifier(): string
    {
        return EmailType::NotificationRsvpAggregated->value;
    }

    public function getTriggerLabel(): string
    {
        return 'admin_email_templates.trigger_notification_rsvp_aggregated';
    }

    public function getDisplayMockData(string $locale): array
    {
        $sample = $this->samples->create($locale);

        return [
            'subject' => 'People you follow plan to attend an event',
            'context' => [
                'username' => $sample->recipientName,
                'attendeeNames' => $sample->attendeeNames,
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
            new RsvpAttendeeMapPresentRule(),
            new UserNotificationsMasterToggleRule(),
            new NotificationToggleEnabledRule('followingUpdates'),
            new RecipientNotAlreadyRsvpdRule(),
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
        /** @var User $recipient */
        $recipient = $context['user'];
        /** @var Event $event */
        $event = $context['event'];
        /** @var array<int, array{recipient: User, attendees: User[]}> $attendeeMap */
        $attendeeMap = $context['attendeeMap'];

        $attendees = $attendeeMap[$recipient->getId()]['attendees'] ?? [];
        if ($attendees === []) {
            return;
        }

        $language = $recipient->getLocale();
        $attendeeNames = implode(', ', array_map(static fn(User $u) => $u->getName(), $attendees));

        $email = new TemplatedEmail();
        $email->from($this->config->getMailerAddress());
        $email->to((string) $recipient->getEmail());
        $email->locale($language);
        $email->context([
            'username' => $recipient->getName(),
            'attendeeNames' => $attendeeNames,
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

        $budgetCutoff = $now->add(new DateInterval('PT12H'));
        $eventStart = DateTimeImmutable::createFromMutable($event->getStart());

        return $budgetCutoff < $eventStart ? $budgetCutoff : $eventStart;
    }

    public function getDueContexts(DateTimeImmutable $now): array
    {
        $events = $this->eventRepo->findUpcomingEventsNeedingRsvpNotification($now, $now->add(new DateInterval('PT48H')));

        $contexts = [];
        foreach ($events as $event) {
            $attendeeMap = $this->buildAttendeeMap($event);
            if ($attendeeMap === []) {
                continue;
            }
            $potentialRecipients = array_column($attendeeMap, 'recipient');
            $contexts[] = new DueContext(['event' => $event, 'attendeeMap' => $attendeeMap], $potentialRecipients);
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
        $event->setRsvpNotificationSentAt(new DateTimeImmutable());
        $this->em->flush();
    }

    public function getPlannedItems(DateTimeImmutable $from, DateTimeImmutable $to): array
    {
        $events = $this->eventRepo->findUpcomingEventsNeedingRsvpNotification($from, $to);

        $items = [];
        foreach ($events as $event) {
            $attendeeMap = $this->buildAttendeeMap($event);
            $eligibleCount = 0;
            foreach ($attendeeMap as $data) {
                $ctx = ['user' => $data['recipient'], 'event' => $event, 'attendeeMap' => $attendeeMap];
                try {
                    $eligible = $this->guardCheck($ctx);
                } catch (InvalidArgumentException) {
                    continue;
                }
                if ($eligible) {
                    $eligibleCount++;
                }
            }

            $expectedTime = max($from, DateTimeImmutable::createFromMutable($event->getStart())->sub(new DateInterval('PT48H')));

            $items[] = new ScheduledMailItem(
                mailType: EmailType::NotificationRsvpAggregated->value,
                label: 'Event: ' . ($event->getTitle('en') ?: ($event->getTranslation()->first() ?: null)?->getTitle() ?? ''),
                expectedTime: $expectedTime,
                expectedRecipients: $eligibleCount,
            );
        }

        return $items;
    }

    /** @return array<int, array{recipient: User, attendees: User[]}> */
    private function buildAttendeeMap(Event $event): array
    {
        $attendees = $event->getRsvp();
        if ($attendees->isEmpty()) {
            return [];
        }

        $map = [];
        foreach ($attendees as $attendee) {
            if (!$attendee instanceof User) {
                continue;
            }
            foreach ($attendee->getFollowers() as $follower) {
                if (!$follower instanceof User) {
                    continue;
                }
                if (!$this->passesFollowerFilters($follower, $attendee, $event)) {
                    continue;
                }
                if (!isset($map[$follower->getId()])) {
                    $map[$follower->getId()] = ['recipient' => $follower, 'attendees' => []];
                }
                $map[$follower->getId()]['attendees'][] = $attendee;
            }
        }

        return $map;
    }

    private function passesFollowerFilters(User $recipient, User $attendee, Event $event): bool
    {
        foreach ($this->followerFilters as $filter) {
            if (!$filter->isFollowerAllowed($recipient, $attendee, $event)) {
                return false;
            }
        }

        return true;
    }
}
