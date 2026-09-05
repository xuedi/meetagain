<?php declare(strict_types=1);

namespace App\Service\Event;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class EventScope
{
    /** @param iterable<EventScopeProviderInterface> $providers */
    public function __construct(
        #[AutowireIterator(EventScopeProviderInterface::class)]
        private iterable $providers,
    ) {}

    /**
     * @template T
     * @param  callable():T $work
     * @return T
     */
    public function runForEvent(int $eventId, callable $work): mixed
    {
        foreach ($this->providers as $provider) {
            $inner = $work;
            $work = static fn(): mixed => $provider->runForEvent($eventId, $inner);
        }

        return $work();
    }
}
