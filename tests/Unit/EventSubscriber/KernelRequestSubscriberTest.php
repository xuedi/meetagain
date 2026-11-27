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
use Symfony\Component\Routing\Exception\ResourceNotFoundException;
use Symfony\Component\Routing\Matcher\UrlMatcherInterface;

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
        $this->subscriber()->onKernelRequest($event);

        // Assert
        static::assertSame('/en/events', $event->getRequest()->getSession()->get('redirectUrl'));
    }

    public function testWritesNoSessionForAGuestWithoutASessionCookie(): void
    {
        // Arrange
        $event = $this->makeEvent('GET', '/en/events', 'app_event_list', hasSessionCookie: false);

        // Act
        $this->subscriber()->onKernelRequest($event);

        // Assert
        static::assertFalse($event->getRequest()->getSession()->isStarted());
        static::assertNull($event->getRequest()->getSession()->get('redirectUrl'));
    }

    public function testSavesRedirectUrlForDocumentNavigation(): void
    {
        // Arrange
        $event = $this->makeEvent('GET', '/en/events', 'app_event_list', ['Sec-Fetch-Dest' => 'document']);

        // Act
        $this->subscriber()->onKernelRequest($event);

        // Assert
        static::assertSame('/en/events', $event->getRequest()->getSession()->get('redirectUrl'));
    }

    public function testDoesNotSaveWhenBrowserFetchesAnImage(): void
    {
        // Arrange
        $event = $this->makeEvent('GET', '/icon.png?v126', 'app_site_icon', ['Sec-Fetch-Dest' => 'image', 'Accept' => 'image/avif,image/webp,*/*']);

        // Act
        $this->subscriber()->onKernelRequest($event);

        // Assert
        static::assertNull($event->getRequest()->getSession()->get('redirectUrl'));
    }

    public function testDoesNotSaveWhenAcceptIsNotHtmlAndFetchDestinationIsMissing(): void
    {
        // Arrange
        $event = $this->makeEvent('GET', '/icon.png', 'app_site_icon', ['Accept' => 'image/*']);

        // Act
        $this->subscriber()->onKernelRequest($event);

        // Assert
        static::assertNull($event->getRequest()->getSession()->get('redirectUrl'));
    }

    public function testKeepsThePageUrlWhenAnImageFetchFollowsIt(): void
    {
        // Arrange
        $session = new Session(new MockArraySessionStorage());
        $page = $this->makeEvent('GET', '/de/admin/cms/24/edit/de', 'app_admin_cms_edit', ['Sec-Fetch-Dest' => 'document'], $session);
        $icon = $this->makeEvent('GET', '/icon.png?v126', 'app_site_icon', ['Sec-Fetch-Dest' => 'image'], $session);
        $subscriber = $this->subscriber();

        // Act
        $subscriber->onKernelRequest($page);
        $subscriber->onKernelRequest($icon);

        // Assert
        static::assertSame('/de/admin/cms/24/edit/de', $session->get('redirectUrl'));
    }

    public function testDoesNotSaveOnPostRequest(): void
    {
        // Arrange
        $event = $this->makeEvent('POST', '/en/events', 'app_event_list');

        // Act
        $this->subscriber()->onKernelRequest($event);

        // Assert
        static::assertNull($event->getRequest()->getSession()->get('redirectUrl'));
    }

    public function testDoesNotSaveOnLoginRoute(): void
    {
        // Arrange
        $event = $this->makeEvent('GET', '/en/login', 'app_login');

        // Act
        $this->subscriber()->onKernelRequest($event);

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
            $this->subscriber()->onKernelRequest($event);

            // Assert
            static::assertNull($event->getRequest()->getSession()->get('redirectUrl'), "Route {$route} should be skipped");
        }
    }

    public function testDoesNotSaveOnApiAjaxOrDevPaths(): void
    {
        // Arrange
        $paths = ['/api/v1/foo', '/ajax/cookie/accept', '/jump/landing', '/_wdt/abc'];

        foreach ($paths as $path) {
            // Act
            $event = $this->makeEvent('GET', $path, 'some_route');
            $this->subscriber()->onKernelRequest($event);

            // Assert
            static::assertNull($event->getRequest()->getSession()->get('redirectUrl'), "Path {$path} should be skipped");
        }
    }

    public function testDoesNotSaveWhenRouteIsMissing(): void
    {
        // Arrange
        $event = $this->makeEvent('GET', '/nope', null);

        // Act
        $this->subscriber()->onKernelRequest($event);

        // Assert
        static::assertNull($event->getRequest()->getSession()->get('redirectUrl'));
    }

    public function testIgnoresSubRequests(): void
    {
        // Arrange
        $request = Request::create('/en/events');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $request->attributes->set('_route', 'app_event_list');
        $event = new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::SUB_REQUEST);

        // Act
        $this->subscriber()->onKernelRequest($event);

        // Assert
        static::assertNull($request->getSession()->get('redirectUrl'));
    }

    public function testAJumpBackPageIsAPathThatMatchesAnEligibleRoute(): void
    {
        // Arrange
        $matcher = $this->createStub(UrlMatcherInterface::class);
        $matcher->method('match')->willReturn(['_route' => 'app_event_list']);

        // Act
        $eligible = $this->subscriber($matcher)->isJumpBackPath('/en/events?page=2');

        // Assert
        static::assertTrue($eligible);
    }

    public function testAnAuthFlowPageIsNoJumpBackPage(): void
    {
        // Arrange
        $matcher = $this->createStub(UrlMatcherInterface::class);
        $matcher->method('match')->willReturn(['_route' => 'app_register']);

        // Act
        $eligible = $this->subscriber($matcher)->isJumpBackPath('/en/register');

        // Assert
        static::assertFalse($eligible);
    }

    public function testAPathWithoutARouteIsNoJumpBackPage(): void
    {
        // Arrange
        $matcher = $this->createStub(UrlMatcherInterface::class);
        $matcher->method('match')->willThrowException(new ResourceNotFoundException());

        // Act
        $eligible = $this->subscriber($matcher)->isJumpBackPath('/nowhere');

        // Assert
        static::assertFalse($eligible);
    }

    private function subscriber(?UrlMatcherInterface $matcher = null): KernelRequestSubscriber
    {
        return new KernelRequestSubscriber($matcher ?? $this->createStub(UrlMatcherInterface::class));
    }

    /**
     * @param array<string, string> $headers
     */
    private function makeEvent(
        string $method,
        string $path,
        ?string $route,
        array $headers = [],
        ?Session $session = null,
        bool $hasSessionCookie = true,
    ): RequestEvent {
        $request = Request::create($path, $method);
        $request->headers->add($headers);
        $session ??= new Session(new MockArraySessionStorage());
        $request->setSession($session);
        if ($hasSessionCookie) {
            $request->cookies->set($session->getName(), 'existing');
        }
        if ($route !== null) {
            $request->attributes->set('_route', $route);
        }

        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }
}
