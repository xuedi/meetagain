<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber;

use App\Entity\User;
use App\EventSubscriber\LoginSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSubscriberTest extends TestCase
{
    private function createLoginEvent(
        UserInterface $user,
        SessionInterface $session,
        ?Response $response = null,
    ): LoginSuccessEvent {
        $request = new Request();
        $request->setSession($session);
        $response ??= new Response();

        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getRequest')->willReturn($request);
        $event->method('getResponse')->willReturn($response);

        return $event;
    }

    public function testGetSubscribedEventsReturnsLoginSuccessEvent(): void
    {
        $events = LoginSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(LoginSuccessEvent::class, $events);
        $this->assertEquals('onLoginSuccess', $events[LoginSuccessEvent::class]);
    }

    public function testOnLoginSuccessReturnsEarlyWhenUserNotUserInstance(): void
    {
        $subscriber = new LoginSubscriber();

        $nonUserMock = $this->createStub(UserInterface::class);
        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->never())->method('set');

        $event = $this->createLoginEvent($nonUserMock, $sessionMock);

        $subscriber->onLoginSuccess($event);
    }

    public function testOnLoginSuccessSetsSessionLocaleFromUser(): void
    {
        $subscriber = new LoginSubscriber();

        $user = new User();
        $user->setLocale('de');
        $user->setOsmConsent(false);

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->once())
            ->method('set')
            ->with('_locale', 'de');

        $event = $this->createLoginEvent($user, $sessionMock);

        $subscriber->onLoginSuccess($event);
    }

    public function testOnLoginSuccessReturnsEarlyWhenUserHasNoOsmConsent(): void
    {
        $subscriber = new LoginSubscriber();

        $user = new User();
        $user->setLocale('en');
        $user->setOsmConsent(false);

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->once())
            ->method('set')
            ->with('_locale', 'en');

        $response = new Response();
        $event = $this->createLoginEvent($user, $sessionMock, $response);

        $subscriber->onLoginSuccess($event);

        // Response should not have any cookies set (returns early before setting consent)
        $this->assertEmpty($response->headers->getCookies());
    }

    public function testOnLoginSuccessSetsConsentCookiesWhenUserHasOsmConsent(): void
    {
        $subscriber = new LoginSubscriber();

        $user = new User();
        $user->setLocale('en');
        $user->setOsmConsent(true);

        $sessionStub = $this->createStub(SessionInterface::class);
        $sessionStub->method('get')->willReturn(null);

        $response = new Response();
        $event = $this->createLoginEvent($user, $sessionStub, $response);

        $subscriber->onLoginSuccess($event);

        $cookies = $response->headers->getCookies();
        $this->assertNotEmpty($cookies);

        $cookieNames = array_map(fn ($c) => $c->getName(), $cookies);
        $this->assertContains('consent_cookies_osm', $cookieNames);
        $this->assertContains('consent_cookies', $cookieNames);
    }
}
