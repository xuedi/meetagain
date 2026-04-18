<?php

declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface NotificationProviderInterface
{
    /**
     * Returns informational bell items for this user (e.g. unread messages, open polls).
     *
     * @return array<NotificationItem>
     */
    public function getNotifications(User $user): array;
}
