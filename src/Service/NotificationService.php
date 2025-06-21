<?php declare(strict_types=1);

namespace App\Service;

use App\Entity\Activity;
use App\Entity\ActivityType;
use App\Entity\User;
use App\Repository\EventRepository;

readonly class NotificationService
{
    public function __construct(private EmailService $emailService, private EventRepository $eventRepo)
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
            if (!$follower instanceof User) {
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
        }
    }
}
