<?php declare(strict_types=1);

namespace Tests\Unit\Service\Member;

use App\Entity\Session\Consent;
use App\Service\Member\ConsentService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\SessionInterface;

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

    // ---- getShowCookieConsent ----

    public function testGetShowCookieConsentNoRequestReturnsTrue(): void
    {
        // Arrange & Act & Assert
        static::assertTrue($this->makeService(null)->getShowCookieConsent());
    }

    public function testGetShowCookieConsentUnknownReturnsTrue(): void
    {
        // Arrange: consent=Unknown → show the banner
        static::assertTrue($this->makeService($this->makeSession('unknown', 'unknown'))->getShowCookieConsent());
    }

    public function testGetShowCookieConsentGrantedReturnsFalse(): void
    {
        // Arrange: consent already given → do not show banner
        static::assertFalse($this->makeService($this->makeSession('granted', 'unknown'))->getShowCookieConsent());
    }

    public function testGetShowCookieConsentDeniedReturnsFalse(): void
    {
        // Arrange: explicitly denied → do not show banner again
        static::assertFalse($this->makeService($this->makeSession('denied', 'unknown'))->getShowCookieConsent());
    }

    // ---- getShowOsm ----

    public function testGetShowOsmNoRequestReturnsTrue(): void
    {
        // Arrange & Act & Assert
        static::assertTrue($this->makeService(null)->getShowOsm());
    }

    public function testGetShowOsmGrantedReturnsTrue(): void
    {
        // Arrange: osm consent granted → show the OSM map
        static::assertTrue($this->makeService($this->makeSession('granted', 'granted'))->getShowOsm());
    }

    public function testGetShowOsmUnknownReturnsFalse(): void
    {
        // Arrange: osm consent unknown → do not show OSM map
        static::assertFalse($this->makeService($this->makeSession('unknown', 'unknown'))->getShowOsm());
    }

    public function testGetShowOsmDeniedReturnsFalse(): void
    {
        // Arrange: osm consent denied → do not show OSM map
        static::assertFalse($this->makeService($this->makeSession('unknown', 'denied'))->getShowOsm());
    }
}
