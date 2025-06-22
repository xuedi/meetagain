<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\User;
use App\Repository\EventRepository;
use Psr\Cache\InvalidArgumentException;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

readonly class NotificationService
{
    public function __construct(
        private EmailService $emailService,
        private EventRepository $eventRepo,
        private TagAwareCacheInterface $appCache,
    )
    {
    }

    public function notify(Activity $activity): void
    {
        $user = $activity->getUser();
        switch ($activity->getType()->value) {
            case ActivityType::RsvpYes->value:
                $this->sendRsvpNotifications($user, $activity->getMeta()['event_id']);
                break;
            default:
                break;
        }
    }

    private function sendRsvpNotifications(?User $user, ?int $eventId = null): void
    {
        if ($user === null || $eventId === null) {
            return;
        }
        $event = $this->eventRepo->findOneBy(['id' => $eventId]);
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
                $this->emailService->sendRsvpNotification(
                    userRsvp: $user,
                    userRecipient: $follower,
                    event: $event
                );
                $this->appCache->get($key, function (ItemInterface $item): string {
                    $item->expiresAfter(3600); // one hour
                    return 'send';
                });
            } catch (InvalidArgumentException $e) {
                //TODO: do some logging
            }
        }
    }
}
