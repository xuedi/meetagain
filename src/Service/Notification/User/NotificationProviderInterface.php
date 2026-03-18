<?php

declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface NotificationProviderInterface
{
    public function getPriority(): int;

    /**
     * @return array<NotificationItem>
     */
    public function getNotifications(User $user): array;
}
