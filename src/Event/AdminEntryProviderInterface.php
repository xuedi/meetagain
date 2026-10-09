<?php declare(strict_types=1);

namespace App\Event;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Offers a way into an admin route for an event that the current request cannot open there. The entry is a form
 * that, when posted, moves the viewer to where the event can be administered and then opens the route. Null when
 * the provider has nothing to offer; the first non-null entry wins.
 */
#[AutoconfigureTag]
interface AdminEntryProviderInterface
{
    /**
     * @param array<string, int|string> $parameters
     */
    public function entryFor(int $eventId, string $route, array $parameters): ?AdminEntry;
}
