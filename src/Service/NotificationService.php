<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\User;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use DateTime;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class NotificationService
{
    private const int HOUR = 3600;
    private const int EIGHT_HOURS = 28800;

    public function __construct(
        private EmailService $emailService,
        private EventRepository $eventRepo,
        private UserRepository $userRepo,
        private TagAwareCacheInterface $appCache,
    ) {
    }

    public function notify(Activity $activity): void
    {
        $user = $activity->getUser();
        switch ($activity->getType()->value) {
            case ActivityType::RsvpYes->value:
                $this->sendRsvp($user, $activity->getMeta()['event_id']);
                $eventId = $activity->getMeta()['event_id'];
                if ($user instanceof User && $eventId !== null) {
                    // TODO: save intend to table that later gets processed by cron for nightly emails
                    //$this->messageBus->dispatch(NotificationRsvp::fromParameter($user, $eventId));
                }
                break;
            case ActivityType::SendMessage->value:
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
                $this->appCache->get($key, function (ItemInterface $item) use ($follower): string {
                    $item->expiresAfter(self::HOUR);
                    if (!$follower->isNotification()) {
                        return 'skip';
                    }
                    if (!$follower->getNotificationSettings()->followingUpdates) {
                        return 'skip';
                    }
                    // $this->emailService->prepareRsvpNotification(
                    //     userRsvp: $user,
                    //     userRecipient: $follower,
                    //     event: $event
                    // );
                    return 'send';
                });
            } catch (InvalidArgumentException) {
                //TODO: do some logging
            }
        }
    }

    private function sendMessage(null|User $user, null|int $userId = null): void
    {
        if (!($user instanceof User) || $userId === null) {
            return;
        }
        $recipient = $this->userRepo->findOneBy(['id' => $userId]);
        if ($recipient === null) {
            return;
        }
        $key = sprintf('message_send_%s_%s', $user->getId(), $recipient->getId());
        $this->appCache->get($key, function (ItemInterface $item) use ($user, $recipient): string {
            $item->expiresAfter(self::EIGHT_HOURS);
            if (!$recipient->isNotification()) {
                return 'skip';
            }
            if (!$recipient->getNotificationSettings()->receivedMessage) {
                return 'skip';
            }
            if ($recipient->getLastLogin() > new DateTime('-2 hours')) {
                return 'skip';
            }
            $this->emailService->prepareMessageNotification(sender: $user, recipient: $recipient);
            $this->emailService->sendQueue(); // TODO: use cron instead
            return 'send';
        });
    }
}
