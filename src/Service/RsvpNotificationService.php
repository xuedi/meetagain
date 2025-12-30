<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Event;
use App\Entity\User;
use App\Repository\EventRepository;
use DateTime;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class RsvpNotificationService
{
    private const int THIRTY_DAYS = 2592000;

    public function __construct(
        private EventRepository $eventRepo,
        private EmailService $emailService,
        private TagAwareCacheInterface $appCache,
    ) {
    }

    public function processUpcomingEvents(int $daysAhead = 7): int
    {
        $start = new DateTime();
        $end = (new DateTime())->modify(sprintf('+%d days', $daysAhead));

        $events = $this->eventRepo->findUpcomingEventsWithinRange($start, $end);
        $totalNotifications = 0;

        foreach ($events as $event) {
            $totalNotifications += $this->notifyFollowersForEvent($event);
        }

        return $totalNotifications;
    }

    public function notifyFollowersForEvent(Event $event): int
    {
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
            return (bool) $this->appCache->get($key, fn (ItemInterface $item) => false);
        } catch (InvalidArgumentException) {
            return false;
        }
    }

    private function markNotificationSent(User $recipient, User $attendee, Event $event): void
    {
        $key = $this->getCacheKey($recipient, $attendee, $event);
        try {
            $this->appCache->get($key, function (ItemInterface $item) {
                $item->expiresAfter(self::THIRTY_DAYS);

                return true;
            }, beta: INF); // beta: INF forces the callback to run and save the new value
        } catch (InvalidArgumentException) {
            // Log error if needed
        }
    }

    private function getCacheKey(User $recipient, User $attendee, Event $event): string
    {
        return sprintf('rsvp_following_notif_%s_%s_%s', $recipient->getId(), $attendee->getId(), $event->getId());
    }
}
