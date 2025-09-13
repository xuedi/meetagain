<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\User;
use App\Message\NotificationRsvp;
use App\Message\PrepareEmail;
use App\Message\SendEmail;
use App\Repository\EventRepository;
use App\Repository\UserRepository;
use DateTime;
use Psr\Cache\InvalidArgumentException;
use Symfony\Component\Messenger\MessageBusInterface;
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
        private MessageBusInterface $messageBus,
    )
    {
    }

    public function notify(Activity $activity): void
    {
        $user = $activity->getUser();
        switch ($activity->getType()->value) {
            case ActivityType::RsvpYes->value:
                $this->sendRsvp($user, $activity->getMeta()['event_id']);
                $eventId = $activity->getMeta()['event_id'];
                if ($user instanceof User && $eventId !== null) {
                    $this->messageBus->dispatch(NotificationRsvp::fromParameter($user, $eventId));
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
                if ($this->appCache->hasItem($key)) {
                    continue;
                }
                if (!$follower->isNotification()) {
                    continue;
                }
                if (!$follower->getNotificationSettings()->followingUpdates) {
                    continue;
                }
                //$this->emailService->prepareRsvpNotification(
                //    userRsvp: $user,
                //    userRecipient: $follower,
                //    event: $event
                //);
                $this->appCache->get($key, function (ItemInterface $item): string {
                    $item->expiresAfter(self::HOUR);
                    return 'send';
                });
            } catch (InvalidArgumentException) {
                //TODO: do some logging
            }
        }
        $this->messageBus->dispatch(new SendEmail());
    }

    private function sendMessage(?User $user, ?int $userId = null): void
    {
        if (!$user instanceof User || $userId === null) {
            return;
        }
        $recipient = $this->userRepo->findOneBy(['id' => $userId]);
        if ($recipient === null) {
            return;
        }
        $key = sprintf('message_send_%s_%s', $user->getId(), $recipient->getId());
        if ($this->appCache->hasItem($key)) {
            return;
        }
        if (!$recipient->isNotification()) {
            return;
        }
        if (!$recipient->getNotificationSettings()->receivedMessage) {
            return;
        }
        if ($recipient->getLastLogin() > new DateTime('-2 hours')) {
            return;
        }
        $this->emailService->sendMessageNotification(
            sender: $user,
            recipient: $recipient
        );
        $this->appCache->get($key, function (ItemInterface $item): string {
            $item->expiresAfter(self::EIGHT_HOURS);
            return 'send';
        });
    }
}
