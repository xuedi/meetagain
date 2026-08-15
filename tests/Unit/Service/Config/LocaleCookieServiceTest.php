<?php declare(strict_types=1);

namespace Tests\Unit\Service\Config;

use App\Entity\Session\Consent;
use App\Enum\ConsentType;
use App\Service\Config\LanguageService;
use App\Service\Config\LocaleCookieService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class LocaleCookieServiceTest extends TestCase
{
    private function createService(array $enabledCodes = ['en', 'de', 'zh']): LocaleCookieService
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getEnabledCodes')->willReturn($enabledCodes);

        return new LocaleCookieService($languageService);
    }

    private function createConsentedRequest(array $cookies = []): Request
    {
        return new Request([], [], [], array_merge([Consent::TYPE_COOKIES => ConsentType::Granted->value], $cookies));
    }

    public function testCreateCookieIsHttpOnlyLaxAndLongLived(): void
    {
        // Arrange
        $service = $this->createService();

        // Act
        $cookie = $service->createCookie('de');

        // Assert
        static::assertSame(LocaleCookieService::COOKIE_NAME, $cookie->getName());
        static::assertSame('de', $cookie->getValue());
        static::assertTrue($cookie->isHttpOnly());
        static::assertSame(Cookie::SAMESITE_LAX, $cookie->getSameSite());
        static::assertGreaterThan(time() + 150 * 24 * 3600, $cookie->getExpiresTime());
    }

    public function testIsConsentGrantedReadsConsentCookie(): void
    {
        // Arrange
        $service = $this->createService();

        // Act & Assert
        static::assertTrue($service->isConsentGranted($this->createConsentedRequest()));
        static::assertFalse($service->isConsentGranted(new Request()));
        static::assertFalse($service->isConsentGranted(
            new Request([], [], [], [Consent::TYPE_COOKIES => ConsentType::Denied->value]),
        ));
    }

    public function testGetValidLocaleReturnsNullWhenCookieMissing(): void
    {
        // Arrange
        $service = $this->createService();

        // Act & Assert
        static::assertNull($service->getValidLocale(new Request()));
    }

    public function testGetValidLocaleReturnsNullForUnknownCode(): void
    {
        // Arrange
        $service = $this->createService();
        $request = new Request([], [], [], [LocaleCookieService::COOKIE_NAME => 'xx']);

        // Act & Assert
        static::assertNull($service->getValidLocale($request));
    }

    public function testGetValidLocaleReturnsEnabledCode(): void
    {
        // Arrange
        $service = $this->createService();
        $request = new Request([], [], [], [LocaleCookieService::COOKIE_NAME => 'de']);

        // Act & Assert
        static::assertSame('de', $service->getValidLocale($request));
    }

    public function testAttachSkipsWriteWithoutConsent(): void
    {
        // Arrange
        $service = $this->createService();
        $response = new Response();

        // Act
        $service->attachIfConsentGranted(new Request(), $response, 'de');

        // Assert
        static::assertCount(0, $response->headers->getCookies());
    }

    public function testAttachSkipsWriteForUnknownLocale(): void
    {
        // Arrange
        $service = $this->createService();
        $response = new Response();

        // Act
        $service->attachIfConsentGranted($this->createConsentedRequest(), $response, 'xx');

        // Assert
        static::assertCount(0, $response->headers->getCookies());
    }

    public function testAttachSkipsWriteWhenCookieAlreadyCurrent(): void
    {
        // Arrange
        $service = $this->createService();
        $response = new Response();

        // Act
        $service->attachIfConsentGranted(
            $this->createConsentedRequest([LocaleCookieService::COOKIE_NAME => 'de']),
            $response,
            'de',
        );

        // Assert
        static::assertCount(0, $response->headers->getCookies());
    }

    public function testAttachWritesCookieWhenConsentGranted(): void
    {
        // Arrange
        $service = $this->createService();
        $response = new Response();

        // Act
        $service->attachIfConsentGranted($this->createConsentedRequest(), $response, 'de');

        // Assert
        $cookies = $response->headers->getCookies();
        static::assertCount(1, $cookies);
        static::assertSame(LocaleCookieService::COOKIE_NAME, $cookies[0]->getName());
        static::assertSame('de', $cookies[0]->getValue());
    }
}
