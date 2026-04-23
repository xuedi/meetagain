<?php

declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use App\Repository\MessageRepository;
use Symfony\Contracts\Translation\TranslatorInterface;

readonly class UserNotificationProvider implements NotificationProviderInterface
{
    public function __construct(
        private MessageRepository $messageRepo,
        private TranslatorInterface $translator,
    ) {}


    public function getNotifications(User $user): array
    {
        $items = [];

        if ($this->messageRepo->hasNewMessages($user)) {
            $unreadCount = $this->getUnreadMessageCount($user);

            $items[] = new NotificationItem(
                label: $this->translator->trans('chrome.notification_unread_messages', ['%count%' => $unreadCount]),
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
