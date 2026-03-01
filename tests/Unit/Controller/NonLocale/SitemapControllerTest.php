<?php declare(strict_types=1);

namespace Tests\Unit\Controller\NonLocale;

use App\Controller\NonLocale\SitemapController;
use App\Service\SitemapService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class SitemapControllerTest extends TestCase
{
    public function testIndexReturnsSitemapResponseForCurrentHost(): void
    {
        // Arrange
        $sitemapXml = '<?xml version="1.0"?><urlset></urlset>';
        $expectedResponse = new Response($sitemapXml, Response::HTTP_OK, ['Content-Type' => 'text/xml']);

        $service = $this->createMock(SitemapService::class);
        $service->expects($this->once())->method('getContent')->with('meetagain.local')->willReturn($expectedResponse);

        $controller = new SitemapController($service);
        $request = Request::create('https://meetagain.local/sitemap.xml');

        // Act
        $response = $controller->index($request);

        // Assert
        $this->assertSame($expectedResponse, $response);
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }
}
