<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber;

use App\EventSubscriber\LocaleSubscriber;
use App\Service\Config\LanguageService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Event\RequestEvent;
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

        static::assertArrayHasKey(KernelEvents::REQUEST, $events);
        static::assertEquals([['onKernelRequest', 20]], $events[KernelEvents::REQUEST]);
    }

    public function testOnKernelRequestReturnsEarlyWhenNoPreviousSession(): void
    {
        $languageService = $this->createLanguageServiceStub('en');
        $subscriber = new LocaleSubscriber($languageService);

        $request = new Request();
        // No session set, so hasPreviousSession() returns false

        $kernelMock = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernelMock, $request, HttpKernelInterface::MAIN_REQUEST);

        // Should not throw any errors, just return early
        $subscriber->onKernelRequest($event);

        static::assertTrue(true); // If we got here, no exception was thrown
    }

    public function testOnKernelRequestSavesLocaleToSessionWhenAttributePresent(): void
    {
        $languageService = $this->createLanguageServiceStub('en');
        $subscriber = new LocaleSubscriber($languageService);

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->expects($this->once())->method('set')->with('_locale', 'de');

        $request = $this->createRequestWithSession($sessionMock);
        $request->attributes->set('_locale', 'de');

        $kernelMock = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernelMock, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);
    }

    public function testOnKernelRequestRestoresLocaleFromSessionWhenNoAttribute(): void
    {
        $languageService = $this->createLanguageServiceStub('en');
        $subscriber = new LocaleSubscriber($languageService);

        $sessionStub = $this->createStub(SessionInterface::class);
        $sessionStub->method('has')->willReturn(true);
        $sessionStub->method('get')->willReturn('fr');

        $request = $this->createRequestWithSession($sessionStub);
        // No _locale attribute set

        $kernelStub = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernelStub, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        static::assertSame('fr', $request->getLocale());
    }

    public function testOnKernelRequestUsesAcceptLanguageHintWhenSessionEmpty(): void
    {
        // No session locale set, but Accept-Language header asks for German.
        // The subscriber should pick `de` (an enabled code) without persisting.
        $languageService = $this->createLanguageServiceStub('en', ['en', 'de', 'zh']);
        $subscriber = new LocaleSubscriber($languageService);

        $sessionMock = $this->createMock(SessionInterface::class);
        $sessionMock->method('getName')->willReturn('PHPSESSID');
        $sessionMock->method('has')->willReturn(false);
        $sessionMock->expects($this->never())->method('set'); // hint not persisted

        $request = new Request([], [], [], ['PHPSESSID' => 'test-session-id']);
        $request->setSession($sessionMock);
        $request->headers->set('Accept-Language', 'fr, es;q=0.5, de;q=0.1');

        $kernelStub = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernelStub, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        static::assertSame('de', $request->getLocale());
    }

    public function testOnKernelRequestFallsBackToFilteredDefaultWhenAcceptLanguageHasNoMatch(): void
    {
        // Accept-Language asks for Japanese, which is not enabled.
        // The subscriber should fall back to the filtered default (`en`).
        $languageService = $this->createLanguageServiceStub('en', ['en', 'de', 'zh']);
        $subscriber = new LocaleSubscriber($languageService);

        $sessionStub = $this->createStub(SessionInterface::class);
        $sessionStub->method('has')->willReturn(false);

        $request = $this->createRequestWithSession($sessionStub);
        $request->headers->set('Accept-Language', 'ja');

        $kernelStub = $this->createStub(HttpKernelInterface::class);
        $event = new RequestEvent($kernelStub, $request, HttpKernelInterface::MAIN_REQUEST);

        $subscriber->onKernelRequest($event);

        // Symfony's getPreferredLanguage returns the first locale in the list
        // when none match, which is 'en' here.
        static::assertSame('en', $request->getLocale());
    }
}
