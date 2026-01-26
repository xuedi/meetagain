<?php declare(strict_types=1);

namespace App\Filter\Member;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for member/user content filters.
 * Plugins can implement this to restrict which members are visible.
 *
 * Multiple filters can be registered - they are composed with AND logic.
 * If any filter restricts a member, they will be hidden.
 */
#[AutoconfigureTag]
interface MemberFilterInterface
{
    /**
     * Get priority for filter ordering.
     * Higher priority filters are applied first.
     * Default: 0
     */
    public function getPriority(): int;

    /**
     * Get the allowed user IDs for the current context.
     *
     * @return array<int>|null Returns:
     *         - null: No filtering (allow all members)
     *         - array<int>: Only these user IDs are allowed
     *         - []: No members allowed (empty result)
     */
    public function getUserIdFilter(): ?array;

    /**
     * Check if a specific member is accessible in the current context.
     *
     * @param int $userId The user ID to check
     * @return bool|null Returns:
     *         - null: No opinion (let other filters decide)
     *         - true: Explicitly allow this member
     *         - false: Explicitly deny this member
     */
    public function isMemberAccessible(int $userId): ?bool;
}
