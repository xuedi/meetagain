<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber;

use App\Entity\Language;
use App\EventSubscriber\LocaleSubscriber;
use App\EventSubscriber\LocaleValidationSubscriber;
use App\Service\Config\LanguageService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\EventListener\RouterListener;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class LocaleValidationSubscriberTest extends TestCase
{
    private function makeEvent(Request $request, bool $isMain = true): RequestEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $type = $isMain ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST;

        return new RequestEvent($kernel, $request, $type);
    }

    private function makeSubscriber(
        bool $isValid = true,
        bool $isFilteredValid = true,
        string $defaultLocale = 'en',
        bool $isKnownLanguage = false,
    ): LocaleValidationSubscriber {
        $lang = $this->createStub(LanguageService::class);
        $lang->method('isValidCode')->willReturn($isValid);
        $lang->method('isFilteredValidCode')->willReturn($isFilteredValid);
        $lang->method('getFilteredDefaultLocale')->willReturn($defaultLocale);
        $lang->method('findByCode')->willReturn($isKnownLanguage ? new Language() : null);

        return new LocaleValidationSubscriber($lang);
    }

    public function testRunsAfterTheRouterAndBeforeTheLocaleIsStored(): void
    {
        // Arrange
        $routerPriority = RouterListener::getSubscribedEvents()[KernelEvents::REQUEST][0][1];
        $storePriority = LocaleSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0][1];

        // Act
        $priority = LocaleValidationSubscriber::getSubscribedEvents()[KernelEvents::REQUEST][0][1];

        // Assert
        static::assertLessThan($routerPriority, $priority);
        static::assertGreaterThan($storePriority, $priority);
    }

    public function testSubRequestIsIgnored(): void
    {
        $event = $this->makeEvent(new Request(), isMain: false);
        $this->makeSubscriber()->onKernelRequest($event);

        static::assertNull($event->getResponse());
    }

    public function testNoLocaleAttributeDoesNothing(): void
    {
        $request = new Request();
        $event = $this->makeEvent($request);
        $this->makeSubscriber()->onKernelRequest($event);

        static::assertNull($event->getResponse());
    }

    public function testInvalidLocaleThrows404(): void
    {
        $this->expectException(NotFoundHttpException::class);
        $this->expectExceptionMessageIsOrContains('Locale "xx" is not available');

        $request = new Request();
        $request->attributes->set('_locale', 'xx');
        $event = $this->makeEvent($request);

        $this->makeSubscriber(isValid: false)->onKernelRequest($event);
    }

    public function testFilteredOutLocaleRedirectsToDefault(): void
    {
        // Arrange
        $request = Request::create('/de/events');
        $request->attributes->set('_locale', 'de');
        $event = $this->makeEvent($request);

        // Act
        $this->makeSubscriber(isValid: true, isFilteredValid: false, defaultLocale: 'en')->onKernelRequest($event);

        // Assert
        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(302, $response->getStatusCode());
        static::assertStringContainsString('/en/events', $response->headers->get('Location'));
    }

    public function testTheRenamedChineseCodeRedirectsPermanentlyAndKeepsPathAndQuery(): void
    {
        // Arrange
        $request = Request::create('/cn/event/37?tab=info');
        $request->attributes->set('_locale', 'cn');
        $event = $this->makeEvent($request);

        // Act
        $this->makeSubscriber(isValid: false)->onKernelRequest($event);

        // Assert
        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(301, $response->getStatusCode());
        static::assertSame('/zh/event/37?tab=info', $response->headers->get('Location'));
    }

    public function testADisabledLanguageRedirectsToTheDefaultInsteadOfAnswering404(): void
    {
        // Arrange
        $request = Request::create('/it/event/40');
        $request->attributes->set('_locale', 'it');
        $event = $this->makeEvent($request);

        // Act
        $this->makeSubscriber(isValid: false, defaultLocale: 'de', isKnownLanguage: true)->onKernelRequest($event);

        // Assert
        $response = $event->getResponse();
        static::assertNotNull($response);
        static::assertSame(302, $response->getStatusCode());
        static::assertSame('/de/event/40', $response->headers->get('Location'));
    }

    public function testValidAndFilteredLocaleDoesNothing(): void
    {
        $request = new Request();
        $request->attributes->set('_locale', 'en');
        $event = $this->makeEvent($request);

        $this->makeSubscriber(isValid: true, isFilteredValid: true)->onKernelRequest($event);

        static::assertNull($event->getResponse());
    }

    public function testLocaleFromLegacyAttributeIsAlsoChecked(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $request = new Request();
        $request->attributes->set('locale', 'zz');
        $event = $this->makeEvent($request);

        $this->makeSubscriber(isValid: false)->onKernelRequest($event);
    }
}
