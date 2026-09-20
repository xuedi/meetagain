<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber;

use App\Entity\Session\Consent;
use App\Entity\User;
use App\Enum\ConsentType;
use App\EventSubscriber\LoginSubscriber;
use App\Service\Config\LocaleCookieService;
use App\Service\Member\ConsentService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;

class LoginSubscriberTest extends TestCase
{
    private function createSubscriber(Request $request, bool $consentGranted = false): LoginSubscriber
    {
        $cookieService = $this->createStub(LocaleCookieService::class);
        $cookieService->method('isConsentGranted')->willReturn($consentGranted);
        $cookieService->method('createCookie')->willReturnCallback(static fn(string $locale): Cookie => new Cookie(LocaleCookieService::COOKIE_NAME, $locale));

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new LoginSubscriber($cookieService, new ConsentService($requestStack));
    }

    private function createRequest(SessionInterface $session): Request
    {
        $request = new Request();
        $request->setSession($session);

        return $request;
    }

    private function createSession(): Session
    {
        return new Session(new MockArraySessionStorage());
    }

    private function createLoginEvent(UserInterface $user, Request $request, ?Response $response): LoginSuccessEvent
    {
        $event = $this->createStub(LoginSuccessEvent::class);
        $event->method('getUser')->willReturn($user);
        $event->method('getRequest')->willReturn($request);
        $event->method('getResponse')->willReturn($response);

        return $event;
    }

    private function createUser(string $locale, bool $osmConsent): User
    {
        $user = new User();
        $user->setLocale($locale);
        $user->setOsmConsent($osmConsent);

        return $user;
    }

    /** @return list<string> */
    private function cookieNames(Response $response): array
    {
        return array_map(static fn(Cookie $cookie): string => $cookie->getName(), $response->headers->getCookies());
    }

    private function osmCookieValue(Response $response): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === Consent::TYPE_OSM) {
                return $cookie->getValue();
            }
        }

        return null;
    }

    public function testGetSubscribedEventsReturnsLoginSuccessEvent(): void
    {
        // Arrange & Act
        $events = LoginSubscriber::getSubscribedEvents();

        // Assert
        static::assertArrayHasKey(LoginSuccessEvent::class, $events);
        static::assertSame('onLoginSuccess', $events[LoginSuccessEvent::class]);
    }

    public function testOnLoginSuccessReturnsEarlyWhenUserNotUserInstance(): void
    {
        // Arrange
        $session = $this->createMock(SessionInterface::class);
        $session->expects($this->never())->method('set');
        $request = $this->createRequest($session);
        $event = $this->createLoginEvent($this->createStub(UserInterface::class), $request, new Response());

        // Act & Assert
        $this->createSubscriber($request)->onLoginSuccess($event);
    }

    public function testOnLoginSuccessSetsSessionLocaleFromUser(): void
    {
        // Arrange
        $session = $this->createSession();
        $request = $this->createRequest($session);
        $event = $this->createLoginEvent($this->createUser('de', false), $request, new Response());

        // Act
        $this->createSubscriber($request)->onLoginSuccess($event);

        // Assert
        static::assertSame('de', $session->get('_locale'));
    }

    public function testOnLoginSuccessSetsLocaleCookieWhenConsentGranted(): void
    {
        // Arrange
        $session = $this->createSession();
        $request = $this->createRequest($session);
        $response = new Response();
        $event = $this->createLoginEvent($this->createUser('de', false), $request, $response);

        // Act
        $this->createSubscriber($request, consentGranted: true)->onLoginSuccess($event);

        // Assert
        static::assertContains(LocaleCookieService::COOKIE_NAME, $this->cookieNames($response));
    }

    public function testOnLoginSuccessGrantsOsmConsentWhenUserHasIt(): void
    {
        // Arrange
        $session = $this->createSession();
        $request = $this->createRequest($session);
        $response = new Response();
        $event = $this->createLoginEvent($this->createUser('en', true), $request, $response);

        // Act
        $this->createSubscriber($request)->onLoginSuccess($event);

        // Assert
        static::assertSame(ConsentType::Granted, Consent::getBySession($session)->getOsm());
        static::assertSame('granted', $this->osmCookieValue($response));
        static::assertContains(Consent::TYPE_COOKIES, $this->cookieNames($response));
    }

    public function testOnLoginSuccessRevokesOsmConsentWhenUserHasNone(): void
    {
        // Arrange
        $session = $this->createSession();
        $consent = new Consent();
        $consent->setOsm(ConsentType::Granted);
        $consent->save($session);
        $request = $this->createRequest($session);
        $response = new Response();
        $event = $this->createLoginEvent($this->createUser('en', false), $request, $response);

        // Act
        $this->createSubscriber($request)->onLoginSuccess($event);

        // Assert
        static::assertSame(ConsentType::Denied, Consent::getBySession($session)->getOsm());
        static::assertSame('denied', $this->osmCookieValue($response));
    }

    public function testOnLoginSuccessWritesSessionConsentWhenResponseIsNull(): void
    {
        // Arrange
        $session = $this->createSession();
        $request = $this->createRequest($session);
        $event = $this->createLoginEvent($this->createUser('en', true), $request, null);

        // Act
        $this->createSubscriber($request)->onLoginSuccess($event);

        // Assert
        static::assertSame(ConsentType::Granted, Consent::getBySession($session)->getOsm());
    }
}
