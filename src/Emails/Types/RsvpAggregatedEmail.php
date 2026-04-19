<?php declare(strict_types=1);

namespace App\Emails\Types;

use App\Emails\DueContext;
use App\Emails\EmailQueueInterface;
use App\Emails\ScheduledEmailInterface;
use App\Emails\ScheduledMailItem;
use App\Entity\Event;
use App\Entity\User;
use App\Enum\EmailType;
use App\Repository\EventRepository;
use App\Service\Config\ConfigService;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;

readonly class RsvpAggregatedEmail implements ScheduledEmailInterface
{
    public function __construct(
        private EmailQueueInterface $queue,
        private ConfigService $config,
        private EventRepository $eventRepo,
        private EntityManagerInterface $em,
    ) {}

    public function getIdentifier(): string
    {
        return EmailType::NotificationRsvpAggregated->value;
    }

    public function getDisplayMockData(): array
    {
        return [
            'subject' => 'People you follow plan to attend an event',
            'context' => [
                'username' => 'John Doe',
                'attendeeNames' => 'Denis Matrens, Jane Smith',
                'eventLocation' => 'NightBar 64',
                'eventDate' => '2025-01-01',
                'eventId' => 1,
                'eventTitle' => 'Go tournament afterparty',
                'host' => 'https://localhost/en',
                'lang' => 'en',
            ],
        ];
    }

    public function guardCheck(array $context): bool
    {
        /** @var User $recipient */
        $recipient = $context['user'];
        /** @var Event $event */
        $event = $context['event'];

        if (!$recipient->isNotification()) {
            return false;
        }
        if (!$recipient->getNotificationSettings()->followingUpdates) {
            return false;
        }
        if ($event->hasRsvp($recipient)) {
            return false;
        }

        return true;
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
            'host' => $this->config->getHost(),
            'lang' => $language,
        ]);

        $this->queue->enqueue($email, EmailType::NotificationRsvpAggregated);
    }

    public function getDueContexts(DateTimeImmutable $now): array
    {
        $events = $this->eventRepo->findUpcomingEventsNeedingRsvpNotification(
            $now,
            $now->add(new DateInterval('PT48H')),
        );

        $contexts = [];
        foreach ($events as $event) {
            $attendeeMap = $this->buildAttendeeMap($event);
            if ($attendeeMap === []) {
                continue;
            }
            $potentialRecipients = array_column($attendeeMap, 'recipient');
            $contexts[] = new DueContext(
                ['event' => $event, 'attendeeMap' => $attendeeMap],
                $potentialRecipients,
            );
        }

        return $contexts;
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
                if ($this->guardCheck($ctx)) {
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
                if (!isset($map[$follower->getId()])) {
                    $map[$follower->getId()] = ['recipient' => $follower, 'attendees' => []];
                }
                $map[$follower->getId()]['attendees'][] = $attendee;
            }
        }

        return $map;
    }
}
