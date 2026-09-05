<?php declare(strict_types=1);

namespace App\Service\Event;

use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface EventScopeProviderInterface
{
    /**
     * Run the callback under whatever narrowing the given event implies, so rows written inside it
     * are attributed the way a request serving that event's page would attribute them. An
     * implementation that cannot resolve the event runs the callback unchanged.
     *
     * @template T
     * @param  callable():T $work
     * @return T
     */
    public function runForEvent(int $eventId, callable $work): mixed;
}
