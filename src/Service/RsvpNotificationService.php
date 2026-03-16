<?php declare(strict_types=1);

namespace App\Service;

use App\CronTaskInterface;
use App\Entity\Event;
use App\Entity\User;
use App\Repository\EventRepository;
use DateInterval;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Cache\InvalidArgumentException;
use Psr\Log\LoggerInterface;
use Symfony\Component\Clock\ClockInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class RsvpNotificationService implements CronTaskInterface
{
    private const int THIRTY_DAYS = 2592000;

    public function __construct(
        private EventRepository $eventRepo,
        private EmailService $emailService,
        private TagAwareCacheInterface $appCache,
        private ConfigService $configService,
        private LoggerInterface $logger,
        private EntityManagerInterface $entityManager,
        private ClockInterface $clock,
    ) {}

    public function runCronTask(OutputInterface $output): void
    {
        $currentHour = (int) $this->clock->now()->format('H');
        if ($currentHour < 7 || $currentHour >= 22) {
            $output->writeln('RSVP notifications skipped: outside allowed hours (07:00-22:00)');

            return;
        }

        $result = $this->processUpcomingEvents();
        $output->writeln('Send RSVP notifications: ' . $result);
        $this->logger->info('RSVP notifications processed', ['result' => $result]);
    }

    public function processUpcomingEvents(): string
    {
        if (!$this->configService->isSendRsvpNotifications()) {
            return 'disabled';
        }

        $now = $this->clock->now();
        $end = $now->add(new DateInterval('PT48H'));

        $events = $this->eventRepo->findUpcomingEventsNeedingRsvpNotification($now, $end);
        $totalNotifications = 0;

        foreach ($events as $event) {
            $totalNotifications += $this->notifyFollowersForEvent($event);

            $event->setRsvpNotificationSentAt(new DateTimeImmutable());
            $this->entityManager->flush();
        }

        return $totalNotifications . ' sent';
    }

    public function notifyFollowersForEvent(Event $event): int
    {
        if (!$this->configService->isSendRsvpNotifications()) {
            return 0;
        }

        $attendees = $event->getRsvp();
        if ($attendees->isEmpty()) {
            return 0;
        }

        /** @var array<int, array<User>> $recipientMap [recipientId => [attendees]] */
        $recipientMap = [];

        foreach ($attendees as $attendee) {
            if (!$attendee instanceof User) {
                continue;
            }

            foreach ($attendee->getFollowers() as $follower) {
                if (!$follower instanceof User) {
                    continue;
                }

                if (!$this->shouldNotify($follower, $attendee, $event)) {
                    continue;
                }

                if (!isset($recipientMap[$follower->getId()])) {
                    $recipientMap[$follower->getId()] = [
                        'recipient' => $follower,
                        'attendees' => [],
                    ];
                }
                $recipientMap[$follower->getId()]['attendees'][] = $attendee;
            }
        }

        $sentCount = 0;
        foreach ($recipientMap as $data) {
            /** @var User $recipient */
            $recipient = $data['recipient'];
            /** @var array<User> $followedAttendees */
            $followedAttendees = $data['attendees'];

            $this->emailService->prepareAggregatedRsvpNotification($recipient, $followedAttendees, $event);

            foreach ($followedAttendees as $attendee) {
                $this->markNotificationSent($recipient, $attendee, $event);
            }
            ++$sentCount;
        }

        return $sentCount;
    }

    private function shouldNotify(User $recipient, User $attendee, Event $event): bool
    {
        if (!$recipient->isNotification()) {
            return false;
        }

        if (!$recipient->getNotificationSettings()->followingUpdates) {
            return false;
        }

        if ($event->hasRsvp($recipient)) {
            return false;
        }

        return !$this->wasNotificationSent($recipient, $attendee, $event);
    }

    private function wasNotificationSent(User $recipient, User $attendee, Event $event): bool
    {
        $key = $this->getCacheKey($recipient, $attendee, $event);
        try {
            return (bool) $this->appCache->get($key, fn(ItemInterface $item) => false);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function markNotificationSent(User $recipient, User $attendee, Event $event): void
    {
        $key = $this->getCacheKey($recipient, $attendee, $event);
        try {
            $this->appCache->get(
                $key,
                function (ItemInterface $item) {
                    $item->expiresAfter(self::THIRTY_DAYS);

                    return true;
                },
                beta: INF,
            ); // beta: INF forces the callback to run and save the new value
        } catch (InvalidArgumentException) {
            // Cache write failures are non-critical - notification tracking continues without cache
        }
    }

    private function getCacheKey(User $recipient, User $attendee, Event $event): string
    {
        return sprintf('rsvp_following_notif_%s_%s_%s', $recipient->getId(), $attendee->getId(), $event->getId());
    }
}
