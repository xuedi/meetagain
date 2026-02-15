<?php declare(strict_types=1);

namespace App\Filter\Admin\Event;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for admin event list filters.
 * Plugins can implement this to restrict which events appear in the admin list.
 *
 * Multiple filters can be registered - they are composed with AND logic.
 * If any filter restricts an event, it will be hidden from the admin list.
 */
#[AutoconfigureTag]
interface AdminEventListFilterInterface
{
    /**
     * Get priority for filter ordering.
     * Higher priority filters are applied first.
     * Default: 0
     */
    public function getPriority(): int;

    /**
     * Get the allowed event IDs for the admin list in the current context.
     *
     * @return array<int>|null Returns:
     *         - null: No filtering (allow all events)
     *         - array<int>: Only these event IDs are allowed
     *         - []: No events allowed (empty result)
     */
    public function getEventIdFilter(): ?array;

    /**
     * Check if a specific event is accessible in the admin context.
     *
     * @param int $eventId The event ID to check
     * @return bool|null Returns:
     *         - null: No opinion (let other filters decide)
     *         - true: Explicitly allow this event
     *         - false: Explicitly deny this event
     */
    public function isEventAccessible(int $eventId): ?bool;

    /**
     * Get debug context information for logging when access is denied.
     *
     * @param int $eventId The event ID being checked
     * @return array<string, mixed> Context information for logging (e.g., current group, domain)
     */
    public function getDebugContext(int $eventId): array;
}
