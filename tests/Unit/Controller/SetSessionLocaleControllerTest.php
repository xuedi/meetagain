<?php declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\NonLocale\SetSessionLocaleController;
use App\Service\Config\LanguageService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Session\SessionInterface;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;

final class SetSessionLocaleControllerTest extends TestCase
{
    public function testValidLocaleIsPersistedToSessionAndRedirectsToRoot(): void
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getEnabledCodes')->willReturn(['en', 'de', 'zh']);

        $session = $this->createMock(SessionInterface::class);
        $session->expects(static::once())->method('set')->with('_locale', 'de');

        $request = new Request();
        $request->setSession($session);

        $controller = new SetSessionLocaleController($languageService);
        $response = $controller->index($request, 'de');

        static::assertInstanceOf(RedirectResponse::class, $response);
        static::assertSame('/', $response->getTargetUrl());
    }

    public function testUnknownLocaleThrowsNotFoundAndDoesNotWriteSession(): void
    {
        $languageService = $this->createStub(LanguageService::class);
        $languageService->method('getEnabledCodes')->willReturn(['en', 'de', 'zh']);

        $session = $this->createMock(SessionInterface::class);
        $session->expects(static::never())->method('set');

        $request = new Request();
        $request->setSession($session);

        $controller = new SetSessionLocaleController($languageService);

        $this->expectException(NotFoundHttpException::class);
        $controller->index($request, 'ja');
    }
}
