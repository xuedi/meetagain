<?php

declare(strict_types=1);

namespace App\Notification;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class NotificationService
{
    public function __construct(
        #[AutowireIterator(NotificationProviderInterface::class)]
        private iterable $providers,
    ) {}

    public function getNotifications(User $user): NotificationSummary
    {
        $allItems = [];
        $providers = iterator_to_array($this->providers);

        usort($providers, fn($a, $b) => $b->getPriority() <=> $a->getPriority());

        foreach ($providers as $provider) {
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
