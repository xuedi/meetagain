<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\UserBadge;
use App\UserBadgeProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class UserBadgeService
{
    /**
     * @param iterable<UserBadgeProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(UserBadgeProviderInterface::class)]
        private iterable $providers,
    ) {}

    /**
     * @return list<UserBadge>
     */
    public function getBadges(int $userId): array
    {
        $badges = [];
        foreach ($this->providers as $provider) {
            $badges = [...$badges, ...$provider->getBadges($userId)];
        }
        return $badges;
    }
}
