<?php

declare(strict_types=1);

namespace App\Tests\Unit\Controller;

use App\Controller\ErrorController;
use DateTimeImmutable;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Twig\Environment;

/**
 * @covers \App\Controller\ErrorController
 */
final class ErrorControllerTest extends TestCase
{
    public function testNotFoundExceptionRenders404Template(): void
    {
        // Arrange
        $request = Request::create('/de/missing');
        $request->setLocale('de');

        $twig = $this->createMock(Environment::class);
        $twig
            ->expects(static::once())
            ->method('render')
            ->with('cms/404.html.twig', static::callback(static function (array $context) {
                return $context['_locale'] === 'de' && is_string($context['message']) && $context['message'] !== '';
            }))
            ->willReturn('<html>404</html>');

        $controller = new ErrorController($twig);

        // Act
        $response = $controller->show(new NotFoundHttpException(), $request);

        // Assert
        static::assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
        static::assertSame('<html>404</html>', $response->getContent());
    }

    public function testGenericExceptionRendersErrorTemplateWithIdAndTimestamp(): void
    {
        // Arrange
        $request = Request::create('/en/anything');

        $capturedContext = null;
        $twig = $this->createMock(Environment::class);
        $twig
            ->expects(static::once())
            ->method('render')
            ->with('cms/error.html.twig', static::callback(static function (array $context) use (&$capturedContext) {
                $capturedContext = $context;
                return true;
            }))
            ->willReturn('<html>500</html>');

        $controller = new ErrorController($twig);

        // Act
        $response = $controller->show(new RuntimeException('boom'), $request);

        // Assert
        static::assertSame(Response::HTTP_INTERNAL_SERVER_ERROR, $response->getStatusCode());
        static::assertSame('<html>500</html>', $response->getContent());
        static::assertIsArray($capturedContext);
        static::assertArrayHasKey('errorId', $capturedContext);
        static::assertArrayHasKey('occurredAt', $capturedContext);
        static::assertIsString($capturedContext['errorId']);
        static::assertNotSame('', $capturedContext['errorId']);
        static::assertInstanceOf(DateTimeImmutable::class, $capturedContext['occurredAt']);
        static::assertSame('UTC', $capturedContext['occurredAt']->getTimezone()->getName());
    }

    #[AllowMockObjectsWithoutExpectations]
    public function testGenericExceptionFallbackErrorIdIsHex(): void
    {
        // Arrange — without a Sentry hub the controller falls back to a freshly
        // generated Sentry event id (32 hex chars, no hyphens).
        $request = Request::create('/en/anything');

        $capturedContext = null;
        $twig = $this->createMock(Environment::class);
        $twig->method('render')->willReturnCallback(static function (string $template, array $context) use (
            &$capturedContext,
        ) {
            $capturedContext = $context;
            return '';
        });

        $controller = new ErrorController($twig);

        // Act
        $controller->show(new RuntimeException('boom'), $request);

        // Assert
        static::assertMatchesRegularExpression('/^[0-9a-f]{32}$/', $capturedContext['errorId']);
    }
}
