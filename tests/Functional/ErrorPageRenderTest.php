<?php declare(strict_types=1);

namespace Tests\Functional;

use App\Controller\ErrorController;
use RuntimeException;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Twig\Environment;

/**
 * Renders cms/error.html.twig through the real Twig service to catch
 * template-level regressions that mocked Twig in unit tests cannot.
 */
class ErrorPageRenderTest extends KernelTestCase
{
    public function test500PageRendersErrorIdAndBackHomeLink(): void
    {
        // Arrange
        self::bootKernel();
        $twig = self::getContainer()->get('twig');
        static::assertInstanceOf(Environment::class, $twig);

        $controller = new ErrorController($twig);
        $request = Request::create('/en/anything');
        $request->setLocale('en');
        $request->setSession(new Session(new MockArraySessionStorage()));
        $requestStack = self::getContainer()->get('request_stack');
        static::assertInstanceOf(RequestStack::class, $requestStack);
        $requestStack->push($request);

        // Act
        $response = $controller->show(new RuntimeException('boom'), $request);
        $html = (string) $response->getContent();

        // Assert
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        static::assertStringContainsString('Something went wrong', $html);
        static::assertStringContainsString('Error reference', $html);
        static::assertStringContainsString('Back to home', $html);
        // The 32-char hex error id appears in the readonly input.
        static::assertMatchesRegularExpression('#value="[0-9a-f]{32}"#', $html);
    }
}
