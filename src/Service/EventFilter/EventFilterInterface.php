<?php declare(strict_types=1);

namespace App\Service\EventFilter;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for event content filters.
 * Plugins can implement this to restrict which events are visible.
 *
 * Multiple filters can be registered - they are composed with AND logic.
 * If any filter restricts an event, it will be hidden.
 */
#[AutoconfigureTag]
interface EventFilterInterface
{
    /**
     * Get priority for filter ordering.
     * Higher priority filters are applied first.
     * Default: 0
     */
    public function getPriority(): int;

    /**
     * Get the allowed event IDs for the current context.
     *
     * @return array<int>|null Returns:
     *         - null: No filtering (allow all events)
     *         - array<int>: Only these event IDs are allowed
     *         - []: No events allowed (empty result)
     */
    public function getEventIdFilter(): ?array;

    /**
     * Check if a specific event is accessible in the current context.
     *
     * @param int $eventId The event ID to check
     * @return bool|null Returns:
     *         - null: No opinion (let other filters decide)
     *         - true: Explicitly allow this event
     *         - false: Explicitly deny this event
     */
    public function isEventAccessible(int $eventId): ?bool;
}
