<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\UserBadge;
use App\Service\Member\UserBadgeService;
use Override;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

final class BadgeExtension extends AbstractExtension
{
    public function __construct(
        private readonly UserBadgeService $badgeService,
    ) {}

    #[Override]
    public function getFunctions(): array
    {
        return [
            new TwigFunction('user_badges', $this->getUserBadges(...)),
        ];
    }

    /** @return list<UserBadge> */
    public function getUserBadges(int $userId): array
    {
        return $this->badgeService->getBadges($userId);
    }
}
