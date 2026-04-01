<?php declare(strict_types=1);

namespace App\Service\Member;

use App\Entity\UserBadge;
use App\Repository\UserRepository;
use App\UserBadgeProviderInterface;

readonly class VerifiedBadgeProvider implements UserBadgeProviderInterface
{
    public function __construct(
        private UserRepository $userRepository,
    ) {}

    /** @return list<UserBadge> */
    public function getBadges(int $userId): array
    {
        $user = $this->userRepository->find($userId);
        if ($user === null || !$user->isVerified()) {
            return [];
        }

        return [
            new UserBadge(
                icon: 'fa-regular fa-user-check',
                title: 'Verified',
                color: 'has-text-success',
            ),
        ];
    }
}
