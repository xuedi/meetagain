<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber;

use App\EventSubscriber\LocaleSubscriber;
use App\Service\Config\LanguageService;
use App\Service\Config\LocaleCookieService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleSubscriberTest extends TestCase
{
    private function createLanguageServiceStub(string $filteredDefaultLocale = 'en', array $enabledCodes = ['en', 'de', 'zh']): LanguageService
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getFilteredDefaultLocale')->willReturn($filteredDefaultLocale);
        $languageService->method('getEnabledCodes')->willReturn($enabledCodes);

        return $languageService;
    }

    private function createCookieServiceStub(?string $validLocale = null): LocaleCookieService
    {
        $cookieService = $this->createStub(LocaleCookieService::class);
        $cookieService->method('getValidLocale')->willReturn($validLocale);

        return $cookieService;
    }

    /**
     * @param SessionInterface&\PHPUnit\Framework\MockObject\Stub $session
     */
    private function createRequestWithSession(SessionInterface $session): Request
    {
        $request = new Request([], [], [], ['PHPSESSID' => 'test-session-id']);
        $session->method('getName')->willReturn('PHPSESSID');
        $request->setSession($session);

        return $request;
    }

    private function createRequestEvent(Request $request): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), $request, HttpKernelInterface::MAIN_REQUEST);
    }

    public function testGetSubscribedEventsReturnsKernelRequestAndResponse(): void
    {
        $events = LocaleSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::REQUEST, $events);
        static::assertArrayHasKey(KernelEvents::RESPONSE, $events);
        static::assertEquals([['onKernelRequest', 20]], $events[KernelEvents::REQUEST]);
        static::assertEquals([['onKernelResponse', 0]], $events[KernelEvents::RESPONSE]);
    }

    public function testOnKernelRequestSavesLocaleToSessionWhenAttributePresent(): void
    {
        $subscriber = new LocaleSubscriber($this->createLanguageServiceStub(), $this->createCookieServiceStub());

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->once())->method('set')->with('_locale', 'de');

        $request = $this->createRequestWithSession($sessionMock);
        $request->attributes->set('_locale', 'de');

        $subscriber->onKernelRequest($this->createRequestEvent($request));
    }

    public function testOnKernelRequestSkipsSessionWriteWhenAttributePresentButNoSession(): void
    {
        $subscriber = new LocaleSubscriber($this->createLanguageServiceStub(), $this->createCookieServiceStub());

        $request = new Request();
        $request->attributes->set('_locale', 'de');

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        static::assertTrue(true);
    }

    public function testOnKernelRequestRestoresLocaleFromSessionWhenNoAttribute(): void
    {
        $subscriber = new LocaleSubscriber($this->createLanguageServiceStub(), $this->createCookieServiceStub('de'));

        $sessionStub = $this->createStub(SessionInterface::class);
        $sessionStub->method('has')->willReturn(true);
        $sessionStub->method('get')->willReturn('fr');

        $request = $this->createRequestWithSession($sessionStub);

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        static::assertSame('fr', $request->getLocale());
    }

    public function testOnKernelRequestUsesCookieLocaleWhenSessionEmpty(): void
    {
        $subscriber = new LocaleSubscriber($this->createLanguageServiceStub(), $this->createCookieServiceStub('de'));

        $sessionStub = $this->createStub(SessionInterface::class);
        $sessionStub->method('has')->willReturn(false);

        $request = $this->createRequestWithSession($sessionStub);

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        static::assertSame('de', $request->getLocale());
    }

    public function testOnKernelRequestUsesCookieLocaleWithoutSession(): void
    {
        $subscriber = new LocaleSubscriber($this->createLanguageServiceStub(), $this->createCookieServiceStub('zh'));

        $request = new Request();

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        static::assertSame('zh', $request->getLocale());
    }

    public function testOnKernelRequestUsesAcceptLanguageHintWhenSessionAndCookieEmpty(): void
    {
        $languageService = $this->createLanguageServiceStub('en', ['en', 'de', 'zh']);
        $subscriber = new LocaleSubscriber($languageService, $this->createCookieServiceStub());

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->method('getName')->willReturn('PHPSESSID');
        $sessionMock->method('has')->willReturn(false);
        $sessionMock->expects($this->never())->method('set');

        $request = new Request([], [], [], ['PHPSESSID' => 'test-session-id']);
        $request->setSession($sessionMock);
        $request->headers->set('Accept-Language', 'fr, es;q=0.5, de;q=0.1');

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        static::assertSame('de', $request->getLocale());
    }

    public function testOnKernelRequestFallsBackToFilteredDefaultWhenAcceptLanguageHasNoMatch(): void
    {
        $languageService = $this->createLanguageServiceStub('en', ['en', 'de', 'zh']);
        $subscriber = new LocaleSubscriber($languageService, $this->createCookieServiceStub());

        $sessionStub = $this->createStub(SessionInterface::class);
        $sessionStub->method('has')->willReturn(false);

        $request = $this->createRequestWithSession($sessionStub);
        $request->headers->set('Accept-Language', 'ja');

        $subscriber->onKernelRequest($this->createRequestEvent($request));

        // Symfony's getPreferredLanguage returns the first locale in the list
        // when none match, which is 'en' here.
        static::assertSame('en', $request->getLocale());
    }

    public function testOnKernelResponseAttachesCookieWhenLocaleAttributePresent(): void
    {
        $cookieService = $this->createMock(LocaleCookieService::class);
        $cookieService->expects($this->once())->method('attachIfConsentGranted');

        $subscriber = new LocaleSubscriber($this->createLanguageServiceStub(), $cookieService);

        $request = new Request();
        $request->attributes->set('_locale', 'de');
        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $subscriber->onKernelResponse($event);
    }

    public function testOnKernelResponseSkipsCookieWhenNoLocaleAttribute(): void
    {
        $cookieService = $this->createMock(LocaleCookieService::class);
        $cookieService->expects($this->never())->method('attachIfConsentGranted');

        $subscriber = new LocaleSubscriber($this->createLanguageServiceStub(), $cookieService);

        $event = new ResponseEvent(
            $this->createStub(HttpKernelInterface::class),
            new Request(),
            HttpKernelInterface::MAIN_REQUEST,
            new Response(),
        );

        $subscriber->onKernelResponse($event);
    }
}
