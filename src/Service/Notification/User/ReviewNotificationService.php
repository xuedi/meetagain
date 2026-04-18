<?php

declare(strict_types=1);

namespace App\Service\Notification\User;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class ReviewNotificationService
{
    public function __construct(
        #[AutowireIterator(ReviewNotificationProviderInterface::class)]
        private iterable $providers,
    ) {}

    /**
     * Returns providers that have at least one item for the user.
     * Each entry is ['provider' => ReviewNotificationProviderInterface, 'items' => ReviewNotificationItem[]].
     *
     * @return array<int, array{provider: ReviewNotificationProviderInterface, items: ReviewNotificationItem[]}>
     */
    public function getProvidersForUser(User $user): array
    {
        $result = [];
        foreach ($this->providers as $provider) {
            $items = $provider->getReviewItems($user);
            if (count($items) > 0) {
                $result[] = ['provider' => $provider, 'items' => $items];
            }
        }

        return $result;
    }

    public function countForUser(User $user): int
    {
        $total = 0;
        foreach ($this->providers as $provider) {
            $total += count($provider->getReviewItems($user));
        }

        return $total;
    }

    public function getProviderByIdentifier(string $identifier): ReviewNotificationProviderInterface
    {
        foreach ($this->providers as $provider) {
            if ($provider->getIdentifier() === $identifier) {
                return $provider;
            }
        }

        throw new \InvalidArgumentException(sprintf('No review provider found with identifier "%s"', $identifier));
    }
}
