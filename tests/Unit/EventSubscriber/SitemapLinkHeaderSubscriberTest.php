<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber;

use App\EventSubscriber\SitemapLinkHeaderSubscriber;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ResponseEvent;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class SitemapLinkHeaderSubscriberTest extends TestCase
{
    public function testAddsSitemapLinkHeaderToPublicHtmlResponse(): void
    {
        // Arrange
        $response = $this->makeHtmlResponse();
        $event = $this->makeResponseEvent(Request::create('/'), $response, main: true);

        // Act
        (new SitemapLinkHeaderSubscriber())->onKernelResponse($event);

        // Assert
        self::assertSame('</sitemap.xml>; rel="sitemap"', $response->headers->get('Link'));
    }

    public function testAppendsToExistingLinkHeader(): void
    {
        // Arrange
        $response = $this->makeHtmlResponse();
        $response->headers->set('Link', '</canonical>; rel="canonical"');
        $event = $this->makeResponseEvent(Request::create('/'), $response, main: true);

        // Act
        (new SitemapLinkHeaderSubscriber())->onKernelResponse($event);

        // Assert
        self::assertSame(
            '</canonical>; rel="canonical", </sitemap.xml>; rel="sitemap"',
            $response->headers->get('Link'),
        );
    }

    public function testSkipsAdminPaths(): void
    {
        // Arrange
        $response = $this->makeHtmlResponse();
        $event = $this->makeResponseEvent(Request::create('/admin/dashboard'), $response, main: true);

        // Act
        (new SitemapLinkHeaderSubscriber())->onKernelResponse($event);

        // Assert
        self::assertFalse($response->headers->has('Link'));
    }

    public function testSkipsNonHtmlResponses(): void
    {
        // Arrange
        $response = new Response('{}', Response::HTTP_OK, ['Content-Type' => 'application/json']);
        $event = $this->makeResponseEvent(Request::create('/api/status'), $response, main: true);

        // Act
        (new SitemapLinkHeaderSubscriber())->onKernelResponse($event);

        // Assert
        self::assertFalse($response->headers->has('Link'));
    }

    public function testSkipsNon200Responses(): void
    {
        // Arrange
        $response = $this->makeHtmlResponse(Response::HTTP_NOT_FOUND);
        $event = $this->makeResponseEvent(Request::create('/missing'), $response, main: true);

        // Act
        (new SitemapLinkHeaderSubscriber())->onKernelResponse($event);

        // Assert
        self::assertFalse($response->headers->has('Link'));
    }

    public function testSkipsSubRequests(): void
    {
        // Arrange
        $response = $this->makeHtmlResponse();
        $event = $this->makeResponseEvent(Request::create('/'), $response, main: false);

        // Act
        (new SitemapLinkHeaderSubscriber())->onKernelResponse($event);

        // Assert
        self::assertFalse($response->headers->has('Link'));
    }

    private function makeHtmlResponse(int $status = Response::HTTP_OK): Response
    {
        return new Response('<html></html>', $status, ['Content-Type' => 'text/html; charset=UTF-8']);
    }

    private function makeResponseEvent(Request $request, Response $response, bool $main): ResponseEvent
    {
        $kernel = $this->createStub(HttpKernelInterface::class);
        $type = $main ? HttpKernelInterface::MAIN_REQUEST : HttpKernelInterface::SUB_REQUEST;

        return new ResponseEvent($kernel, $request, $type, $response);
    }
}
