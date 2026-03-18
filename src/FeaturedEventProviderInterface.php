<?php declare(strict_types=1);

namespace App;

use App\Entity\Event;

/**
 * Interface for plugins to provide custom featured event lists.
 * Plugins can implement this to override the default featured events logic.
 */
interface FeaturedEventProviderInterface
{
    /**
     * Get the list of featured events.
     *
     * @return array<Event>
     */
    public function getFeaturedEvents(): array;

    /**
     * Check if this provider should be used for the current request context.
     * Return true to override default featured events logic.
     */
    public function shouldProvide(): bool;

    /**
     * Get priority for this provider (higher = used first).
     * Allows multiple providers to be registered with priority ordering.
     */
    public function getPriority(): int;
}
