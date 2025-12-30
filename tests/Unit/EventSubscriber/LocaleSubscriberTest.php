<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber;

use App\EventSubscriber\LocaleSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriberTest extends TestCase
{
    /**
     * @param SessionInterface&\PHPUnit\Framework\MockObject\Stub $session
     */
    private function createRequestWithSession(SessionInterface $session): Request
    {
        // Create request with session cookie to simulate previous session
        $request = new Request([], [], [], ['PHPSESSID' => 'test-session-id']);
        $session->method('getName')->willReturn('PHPSESSID');
        $request->setSession($session);

        return $request;
    }

    public function testGetSubscribedEventsReturnsKernelRequest(): void
    {
        $events = LocaleSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::REQUEST, $events);
        $this->assertEquals([['onKernelRequest', 250]], $events[KernelEvents::REQUEST]);
    }

    public function testOnKernelRequestReturnsEarlyWhenNoPreviousSession(): void
    {
        $subscriber = new LocaleSubscriber('en');

        $request = new Request();
        // No session set, so hasPreviousSession() returns false

        $kernelMock = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernelMock, $request, HttpKernelInterface::MAIN_REQUEST);

        // Should not throw any errors, just return early
        $subscriber->onKernelRequest($event);

        $this->assertTrue(true); // If we got here, no exception was thrown
    }

    public function testOnKernelRequestSavesLocaleToSessionWhenAttributePresent(): void
    {
        $subscriber = new LocaleSubscriber('en');

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->once())
            ->method('set')
            ->with('_locale', 'de');

        $request = $this->createRequestWithSession($sessionMock);
        $request->attributes->set('_locale', 'de');

        $kernelMock = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernelMock, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestRestoresLocaleFromSessionWhenNoAttribute(): void
    {
        $subscriber = new LocaleSubscriber('en');

        $sessionStub = $this->createStub(SessionInterface::class);
        $sessionStub->method('get')
            ->with('_locale', 'en')
            ->willReturn('fr');

        $request = $this->createRequestWithSession($sessionStub);
        // No _locale attribute set

        $kernelStub = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernelStub, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        $this->assertEquals('fr', $request->getLocale());
    }

    public function testOnKernelRequestUsesDefaultLocaleWhenSessionEmpty(): void
    {
        $subscriber = new LocaleSubscriber('es');

        $sessionStub = $this->createStub(SessionInterface::class);
        $sessionStub->method('get')
            ->with('_locale', 'es')
            ->willReturn('es'); // Returns the default

        $request = $this->createRequestWithSession($sessionStub);

        $kernelStub = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernelStub, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        $this->assertEquals('es', $request->getLocale());
    }
}
