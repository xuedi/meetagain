<?php declare(strict_types=1);

namespace App\Filter\Admin\Member;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for admin member list filters.
 * Plugins can implement this to restrict which members appear in the admin list.
 *
 * Multiple filters can be registered - they are composed with AND logic.
 * If any filter restricts a member, it will be hidden from the admin list.
 */
#[AutoconfigureTag]
interface AdminMemberListFilterInterface
{
    /**
     * Get priority for filter ordering.
     * Higher priority filters are applied first.
     * Default: 0
     */
    public function getPriority(): int;

    /**
     * Get the allowed user IDs for the admin list in the current context.
     *
     * @return array<int>|null Returns:
     *         - null: No filtering (allow all members)
     *         - array<int>: Only these user IDs are allowed
     *         - []: No members allowed (empty result)
     */
    public function getUserIdFilter(): ?array;

    /**
     * Check if a specific member is accessible in the admin context.
     *
     * @param int $userId The user ID to check
     * @return bool|null Returns:
     *         - null: No opinion (let other filters decide)
     *         - true: Explicitly allow this member
     *         - false: Explicitly deny this member
     */
    public function isMemberAccessible(int $userId): ?bool;

    /**
     * Get debug context information for logging when access is denied.
     *
     * @param int $userId The user ID being checked
     * @return array<string, mixed> Context information for logging (e.g., current group, domain)
     */
    public function getDebugContext(int $userId): array;
}
