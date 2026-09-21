<?php declare(strict_types=1);

namespace App\Activity;

use App\Activity\Messages\RsvpYes;
use App\Activity\Messages\SendMessage;
use App\Entity\Activity;
use App\Entity\User;
use App\Enum\EmailType;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use App\Service\Email\EmailService;
use DateTimeImmutable;
use Psr\Cache\InvalidArgumentException as CacheInvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class NotificationService
{
    private const int HOUR = 3600;
    private const string MESSAGE_PING_DEADLINE = '+6 hours';

    public function __construct(
        private EventRepository $eventRepo,
        private UserRepository $userRepo,
        private TagAwareCacheInterface $appCache,
        private EmailService $emailService,
    ) {}

    public function notify(Activity $activity): void
    {
        $user = $activity->getUser();
        switch ($activity->getType()) {
            case RsvpYes::TYPE:
                $this->sendRsvp($user, $activity->getMeta()['event_id']);
                $eventId = $activity->getMeta()['event_id'];
                if ($user instanceof User && $eventId !== null) {
                    // TODO: save intend to table that later gets processed by cron for nightly emails
                    // $this->messageBus->dispatch(NotificationRsvp::fromParameter($user, $eventId));
                }
                break;
            case SendMessage::TYPE:
                $this->sendMessage($user, $activity->getMeta()['user_id']);
                break;
            default:
                break;
        }
    }

    public function sendRsvp(User $user, int $eventId): void
    {
        $event = $this->eventRepo->findOneBy(['id' => $eventId]);
        if ($event === null) {
            return;
        }
        foreach ($user->getFollowers() as $follower) {
            try {
                $key = sprintf('rsvp_notification_send_%s_%s_%s', $user->getId(), $follower->getId(), $event->getId());
                $this->appCache->get($key, static function (ItemInterface $item) use ($follower): string {
                    $item->expiresAfter(self::HOUR);
                    if (!$follower->isNotification()) {
                        return 'skip';
                    }
                    if (!$follower->getNotificationSettings()->followingUpdates) {
                        return 'skip';
                    }

                    return 'send';
                });
            } catch (CacheInvalidArgumentException) {
                continue; // Cache write failure for RSVP notification tracking - non-critical, continue without cache
            }
        }
    }

    private function sendMessage(?User $user, ?int $userId = null): void
    {
        if (!$user instanceof User || $userId === null) {
            return;
        }
        $recipient = $this->userRepo->findOneBy(['id' => $userId]);
        $address = $recipient?->getEmail();
        if ($address === null) {
            return;
        }

        $this->emailService->dispatchPush(EmailType::NotificationMessage->value, $address, new DateTimeImmutable(self::MESSAGE_PING_DEADLINE));
    }
}
