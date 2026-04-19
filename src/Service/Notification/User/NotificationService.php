<?php

declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class NotificationService
{
    /**
     * @param iterable<NotificationProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(NotificationProviderInterface::class)]
        private iterable $providers,
    ) {}

    public function getNotifications(User $user): NotificationSummary
    {
        $allItems = [];

        foreach ($this->providers as $provider) {
            $items = $provider->getNotifications($user);
            $allItems = [...$allItems, ...$items];
        }

        $totalCount = count($allItems);

        return new NotificationSummary($allItems, $totalCount);
    }

    public function getTotalCount(User $user): int
    {
        return $this->getNotifications($user)->totalCount;
    }

    public function hasNotifications(User $user): bool
    {
        return $this->getNotifications($user)->hasNotifications();
    }
}
