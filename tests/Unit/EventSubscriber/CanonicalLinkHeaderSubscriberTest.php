<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber;

use App\EventSubscriber\CanonicalLinkHeaderSubscriber;
use App\Service\Seo\CanonicalUrlService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class CanonicalLinkHeaderSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsWiresKernelResponse(): void
    {
        $events = CanonicalLinkHeaderSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::RESPONSE, $events);
        static::assertSame(['onKernelResponse', 0], $events[KernelEvents::RESPONSE]);
    }

    public function testSubRequestsAreIgnored(): void
    {
        // Arrange
        $canonical = $this->createMock(CanonicalUrlService::class);
        $canonical->expects($this->never())->method('getCanonicalUrl');

        $subscriber = new CanonicalLinkHeaderSubscriber($canonical);
        $event = $this->createEvent(new Response(), HttpKernelInterface::SUB_REQUEST);

        // Act
        $subscriber->onKernelResponse($event);

        // Assert
        static::assertFalse($event->getResponse()->headers->has('Link'));
    }

    public function testNonHtmlContentTypeIsIgnored(): void
    {
        // Arrange
        $canonical = $this->createMock(CanonicalUrlService::class);
        $canonical->expects($this->never())->method('getCanonicalUrl');

        $subscriber = new CanonicalLinkHeaderSubscriber($canonical);
        $response = new Response('{}', 200, ['Content-Type' => 'application/json']);

        // Act
        $subscriber->onKernelResponse($this->createEvent($response));

        // Assert
        static::assertFalse($response->headers->has('Link'));
    }

    public function testRedirectResponsesAreIgnored(): void
    {
        // Arrange
        $canonical = $this->createMock(CanonicalUrlService::class);
        $canonical->expects($this->never())->method('getCanonicalUrl');

        $subscriber = new CanonicalLinkHeaderSubscriber($canonical);
        $response = new Response('', 302, ['Content-Type' => 'text/html']);

        // Act
        $subscriber->onKernelResponse($this->createEvent($response));

        // Assert
        static::assertFalse($response->headers->has('Link'));
    }

    public function testHtmlMainResponseGetsCanonicalLinkHeader(): void
    {
        // Arrange
        $canonical = $this->createStub(CanonicalUrlService::class);
        $canonical->method('getCanonicalUrl')->willReturn('https://example.test/foo');

        $subscriber = new CanonicalLinkHeaderSubscriber($canonical);
        $response = new Response('<html></html>', 200, ['Content-Type' => 'text/html; charset=utf-8']);

        // Act
        $subscriber->onKernelResponse($this->createEvent($response));

        // Assert
        static::assertSame('<https://example.test/foo>; rel="canonical"', $response->headers->get('Link'));
    }

    private function createEvent(Response $response, int $type = HttpKernelInterface::MAIN_REQUEST): ResponseEvent
    {
        return new ResponseEvent($this->createStub(HttpKernelInterface::class), Request::create('/'), $type, $response);
    }
}
