<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber;

use App\EventSubscriber\KernelRequestSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelEvents;

class KernelRequestSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsWiresKernelRequestAtPriority10(): void
    {
        $events = KernelRequestSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::REQUEST, $events);
        static::assertSame([['onKernelRequest', 10]], $events[KernelEvents::REQUEST]);
    }
}
