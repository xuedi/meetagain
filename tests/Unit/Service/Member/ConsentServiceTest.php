<?php declare(strict_types=1);

namespace Tests\Unit\Service\Member;

use App\Entity\Session\Consent;
use App\Enum\ConsentType;
use App\Service\Member\ConsentService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;

class ConsentServiceTest extends TestCase
{
    private function makeService(?SessionInterface $session): ConsentService
    {
        $requestStack = $this->createStub(RequestStack::class);
        if ($session === null) {
            $requestStack->method('getCurrentRequest')->willReturn(null);
        }
        if ($session !== null) {
            $request = $this->createStub(Request::class);
            $request->method('getSession')->willReturn($session);
            $requestStack->method('getCurrentRequest')->willReturn($request);
        }

        return new ConsentService(requestStack: $requestStack);
    }

    private function makeSession(string $cookies, string $osm): SessionInterface
    {
        $json = json_encode([Consent::TYPE_COOKIES => $cookies, Consent::TYPE_OSM => $osm]);
        $session = $this->createStub(SessionInterface::class);
        $session->method('get')->willReturn($json);

        return $session;
    }

    public function testGetShowOsmNoRequestReturnsTrue(): void
    {
        // Arrange & Act & Assert
        static::assertTrue($this->makeService(null)->getShowOsm());
    }

    public function testGetShowOsmGrantedReturnsTrue(): void
    {
        // Arrange
        static::assertTrue($this->makeService($this->makeSession('granted', 'granted'))->getShowOsm());
    }

    public function testGetShowOsmUnknownReturnsFalse(): void
    {
        // Arrange
        static::assertFalse($this->makeService($this->makeSession('unknown', 'unknown'))->getShowOsm());
    }

    public function testGetShowOsmDeniedReturnsFalse(): void
    {
        // Arrange
        static::assertFalse($this->makeService($this->makeSession('unknown', 'denied'))->getShowOsm());
    }

    private function makeRealSessionService(Session $session): ConsentService
    {
        $request = new Request();
        $request->setSession($session);
        $requestStack = new RequestStack();
        $requestStack->push($request);

        return new ConsentService(requestStack: $requestStack);
    }

    private function cookieValue(Response $response, string $name): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $name) {
                return $cookie->getValue();
            }
        }

        return null;
    }

    public function testSetShowOsmGrantedWritesSession(): void
    {
        // Arrange
        $session = new Session(new MockArraySessionStorage());

        // Act
        $this->makeRealSessionService($session)->setShowOsm(true);

        // Assert
        static::assertSame(ConsentType::Granted, Consent::getBySession($session)->getOsm());
    }

    public function testSetShowOsmDeniedOverwritesAGrantedSession(): void
    {
        // Arrange
        $session = new Session(new MockArraySessionStorage());
        $consent = new Consent();
        $consent->setOsm(ConsentType::Granted);
        $consent->save($session);

        // Act
        $this->makeRealSessionService($session)->setShowOsm(false);

        // Assert
        static::assertSame(ConsentType::Denied, Consent::getBySession($session)->getOsm());
    }

    public function testSetShowOsmKeepsTheCookieBannerAnswer(): void
    {
        // Arrange
        $session = new Session(new MockArraySessionStorage());
        $consent = new Consent();
        $consent->setCookies(ConsentType::Granted);
        $consent->save($session);

        // Act
        $this->makeRealSessionService($session)->setShowOsm(true);

        // Assert
        static::assertSame(ConsentType::Granted, Consent::getBySession($session)->getCookies());
    }

    public function testSetShowOsmPutsBothConsentCookiesOnTheResponse(): void
    {
        // Arrange
        $session = new Session(new MockArraySessionStorage());
        $response = new Response();

        // Act
        $this->makeRealSessionService($session)->setShowOsm(true, $response);

        // Assert
        static::assertSame('granted', $this->cookieValue($response, Consent::TYPE_OSM));
        static::assertNotNull($this->cookieValue($response, Consent::TYPE_COOKIES));
    }

    public function testSetShowOsmDeniedWritesADeniedCookie(): void
    {
        // Arrange
        $session = new Session(new MockArraySessionStorage());
        $response = new Response();

        // Act
        $this->makeRealSessionService($session)->setShowOsm(false, $response);

        // Assert
        static::assertSame('denied', $this->cookieValue($response, Consent::TYPE_OSM));
    }

    public function testSetShowOsmWithoutARequestTouchesNothing(): void
    {
        // Arrange
        $response = new Response();

        // Act
        $this->makeService(null)->setShowOsm(true, $response);

        // Assert
        static::assertSame([], array_map(static fn(Cookie $cookie): string => $cookie->getName(), $response->headers->getCookies()));
    }
}
