<?php declare(strict_types=1);

namespace App\Service\Item;

use App\Entity\Event;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class SeedEventScope
{
    /**
     * @param iterable<SeedEventScopeInterface> $scopes
     */
    public function __construct(
        #[AutowireIterator(SeedEventScopeInterface::class)]
        private iterable $scopes,
    ) {}

    /**
     * @param array<Event> $events
     *
     * @return list<Event>
     */
    public function filter(array $events, string $pluginKey): array
    {
        $allowed = null;

        foreach ($this->scopes as $scope) {
            $ids = $scope->allowedEventIds($pluginKey);
            if ($ids === null) {
                continue;
            }

            $allowed = $allowed === null ? array_values($ids) : array_values(array_intersect($allowed, $ids));
        }

        if ($allowed === null) {
            return array_values($events);
        }

        return array_values(array_filter($events, static fn(Event $event): bool => in_array((int) $event->getId(), $allowed, true)));
    }
}
