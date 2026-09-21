<?php declare(strict_types=1);

namespace Tests\Unit\Metrics;

use App\Metrics\Dbal\Stats;
use App\Metrics\Recorder;
use App\Metrics\TerminateSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class TerminateSubscriberTest extends TestCase
{
    public function testRequestIsSentWithRouteStatusClassAndQueryCount(): void
    {
        // Arrange
        $listener = new UdpListener();
        $stats = new Stats();
        $stats->record((int) hrtime(true));
        $stats->record((int) hrtime(true));
        $subscriber = new TerminateSubscriber(new Recorder($listener->dsn(), 'prod'), $stats);
        $request = Request::create('https://example.org/en/events?secret=1', 'GET');
        $request->attributes->set('_route', 'app_event');
        $event = new TerminateEvent($this->createStub(HttpKernelInterface::class), $request, new Response('', 404));

        // Act
        $subscriber->onKernelTerminate($event);

        // Assert
        $line = explode("\n", $listener->receive()[0])[0];
        static::assertStringStartsWith('http_request,app=test,env=prod,route=app_event,method=GET,status=4xx,host=example.org ', $line);
        static::assertStringContainsString('db_queries=2i', $line);
        static::assertStringNotContainsString('secret', $line);
    }

    public function testUnmatchedRouteIsTaggedAsNone(): void
    {
        // Arrange
        $listener = new UdpListener();
        $subscriber = new TerminateSubscriber(new Recorder($listener->dsn(), 'prod'), new Stats());
        $event = new TerminateEvent($this->createStub(HttpKernelInterface::class), Request::create('/nope'), new Response());

        // Act
        $subscriber->onKernelTerminate($event);

        // Assert
        static::assertStringContainsString(',route=_none,', $listener->receive()[0]);
    }
}
