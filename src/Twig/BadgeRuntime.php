<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\UserBadge;
use App\Service\Member\UserBadgeService;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class BadgeRuntime implements RuntimeExtensionInterface
{
    public function __construct(
        private UserBadgeService $badgeService,
    ) {}

    /** @return list<UserBadge> */
    public function getUserBadges(int $userId): array
    {
        return $this->badgeService->getBadges($userId);
    }
}
