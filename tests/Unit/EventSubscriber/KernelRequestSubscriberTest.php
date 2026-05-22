<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber;

use App\EventSubscriber\KernelRequestSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class KernelRequestSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsWiresKernelRequestAtPriority10(): void
    {
        // Arrange & Act
        $events = KernelRequestSubscriber::getSubscribedEvents();

        // Assert
        static::assertArrayHasKey(KernelEvents::REQUEST, $events);
        static::assertSame([['onKernelRequest', 10]], $events[KernelEvents::REQUEST]);
    }

    public function testSavesRedirectUrlForRegularGetRoute(): void
    {
        // Arrange
        $event = $this->makeEvent('GET', '/en/events', 'app_event_list');

        // Act
        (new KernelRequestSubscriber())->onKernelRequest($event);

        // Assert
        static::assertSame('/en/events', $event->getRequest()->getSession()->get('redirectUrl'));
    }

    public function testDoesNotSaveOnPostRequest(): void
    {
        // Arrange
        $event = $this->makeEvent('POST', '/en/events', 'app_event_list');

        // Act
        (new KernelRequestSubscriber())->onKernelRequest($event);

        // Assert
        static::assertNull($event->getRequest()->getSession()->get('redirectUrl'));
    }

    public function testDoesNotSaveOnLoginRoute(): void
    {
        // Arrange
        $event = $this->makeEvent('GET', '/en/login', 'app_login');

        // Act
        (new KernelRequestSubscriber())->onKernelRequest($event);

        // Assert
        static::assertNull($event->getRequest()->getSession()->get('redirectUrl'));
    }

    public function testDoesNotSaveOnAuthFlowRoutes(): void
    {
        // Arrange
        $routes = [
            'app_register' => '/en/register',
            'app_register_confirm_email' => '/en/register/verify/abc',
            'app_reset' => '/en/reset',
            'app_reset_password' => '/en/reset/verify/abc',
            'app_security_logout' => '/en/logout',
            'app_security_blocked' => '/en/security/blocked',
            'app_jump_landing' => '/jump-landing',
            'app_jump_forwarder' => '/jump-forwarder',
        ];

        foreach ($routes as $route => $path) {
            // Act
            $event = $this->makeEvent('GET', $path, $route);
            (new KernelRequestSubscriber())->onKernelRequest($event);

            // Assert
            static::assertNull(
                $event->getRequest()->getSession()->get('redirectUrl'),
                "Route {$route} should be skipped",
            );
        }
    }

    public function testDoesNotSaveOnApiAjaxOrDevPaths(): void
    {
        // Arrange
        $paths = ['/api/v1/foo', '/ajax/cookie/accept', '/jump/landing', '/_wdt/abc'];

        foreach ($paths as $path) {
            // Act
            $event = $this->makeEvent('GET', $path, 'some_route');
            (new KernelRequestSubscriber())->onKernelRequest($event);

            // Assert
            static::assertNull(
                $event->getRequest()->getSession()->get('redirectUrl'),
                "Path {$path} should be skipped",
            );
        }
    }

    public function testDoesNotSaveWhenRouteIsMissing(): void
    {
        // Arrange
        $event = $this->makeEvent('GET', '/nope', null);

        // Act
        (new KernelRequestSubscriber())->onKernelRequest($event);

        // Assert
        static::assertNull($event->getRequest()->getSession()->get('redirectUrl'));
    }

    public function testIgnoresSubRequests(): void
    {
        // Arrange
        $request = Request::create('/en/events');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->attributes->set('_route', 'app_event_list');
        $event = new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::SUB_REQUEST,
        );

        // Act
        (new KernelRequestSubscriber())->onKernelRequest($event);

        // Assert
        static::assertNull($request->getSession()->get('redirectUrl'));
    }

    private function makeEvent(string $method, string $path, ?string $route): RequestEvent
    {
        $request = Request::create($path, $method);
        $request->setSession(new Session(new MockArraySessionStorage()));
        if ($route !== null) {
            $request->attributes->set('_route', $route);
        }

        return new RequestEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
        );
    }
}
