<?php

declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use App\Repository\MessageRepository;

readonly class UserNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private MessageRepository $messageRepo,
    ) {}

    public function getPriority(): int
    {
        return 200;
    }

    public function getNotifications(User $user): array
    {
        $items = [];

        if ($this->messageRepo->hasNewMessages($user)) {
            $unreadCount = $this->getUnreadMessageCount($user);

            $items[] = new NotificationItem(
                label: $unreadCount . ' Unread Message' . ($unreadCount > 1 ? 's' : ''),
                icon: 'fa-envelope',
                route: 'app_profile_messages',
            );
        }

        return $items;
    }

    private function getUnreadMessageCount(User $user): int
    {
        return $this->messageRepo->count(['receiver' => $user, 'wasRead' => false]);
    }
}
