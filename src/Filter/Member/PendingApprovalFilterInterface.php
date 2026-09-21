<?php declare(strict_types=1);

namespace App\Filter\Member;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the accounts awaiting approval to those a reviewer may decide. Implementations may only remove accounts;
 * null means no opinion. When no implementation has an opinion, only admins review.
 */
#[AutoconfigureTag]
interface PendingApprovalFilterInterface
{
    /**
     * @param list<User> $pendingUsers
     * @return list<User>|null
     */
    public function filterForReviewer(array $pendingUsers, User $reviewer): ?array;
}
