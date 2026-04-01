<?php declare(strict_types=1);

namespace App;

use App\Entity\UserBadge;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface UserBadgeProviderInterface
{
    /**
     * Returns badges to display for the given user.
     *
     * @return list<UserBadge>
     */
    public function getBadges(int $userId): array;
}
