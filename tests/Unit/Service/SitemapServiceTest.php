<?php
declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Service\CmsService;
use App\Service\SitemapService;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class SitemapServiceTest extends TestCase
{
    private MockObject|Environment $environmentMock;
    private MockObject|CmsService $cmsServiceMock;
    private MockObject|EventRepository $eventRepositoryMock;
    private MockObject|ParameterBagInterface $parameterBagInterfaceMock;
    private SitemapService $subject;

    protected function setUp(): void
    {
        $this->environmentMock = $this->createMock(Environment::class);
        $this->cmsServiceMock = $this->createStub(CmsService::class);
        $this->eventRepositoryMock = $this->createStub(EventRepository::class);
        $this->parameterBagInterfaceMock = $this->createMock(ParameterBagInterface::class);

        $this->subject = new SitemapService(
            twig: $this->environmentMock,
            cms: $this->cmsServiceMock,
            events: $this->eventRepositoryMock,
            appParams: $this->parameterBagInterfaceMock,
        );
    }

    public function testGetContent(): void
    {
        // Test data
        $host = 'example.com';
        $locales = ['en', 'de'];
        $renderedContent = '<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>';

        // Use a stub for CMS sites (no interaction expectations needed)
        $cmsSite = $this->createStub(\App\Entity\Cms::class);
        $cmsSite->method('getSlug')->willReturn('about');
        $cmsSite->method('getCreatedAt')->willReturn(new DateTimeImmutable('2025-01-01'));

        $this->cmsServiceMock->method('getSites')->willReturn([$cmsSite]);

        // Use a stub for events (no interaction expectations needed)
        $event = $this->createStub(Event::class);
        $event->method('getId')->willReturn(123);
        $event->method('getStart')->willReturn(new DateTime('2025-02-01'));

        $this->eventRepositoryMock->method('findAll')->willReturn([$event]);

        // Set up parameter bag to return locales
        $this->parameterBagInterfaceMock
            ->expects($this->once())
            ->method('get')
            ->with('kernel.enabled_locales')
            ->willReturn($locales);

        // Capture the sites array passed to the template renderer
        $capturedSites = null;

        // Set up twig to render the template and capture the sites parameter
        $this->environmentMock
            ->expects($this->once())
            ->method('render')
            ->with(
                $this->equalTo('sitemap/index.xml.twig'),
                $this->callback(function ($params) use (&$capturedSites) {
                    $capturedSites = $params['sites'] ?? null;
                    return isset($params['sites']);
                }),
            )
            ->willReturn($renderedContent);

        // Call the method
        $response = $this->subject->getContent($host);

        // Assert response properties
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('text/xml', $response->headers->get('Content-Type'));
        $this->assertSame($renderedContent, $response->getContent());

        // Verify the sites array structure
        $this->assertNotNull($capturedSites);
        $this->assertIsArray($capturedSites);

        // We should have sites for each locale (en, de) from:
        // - CMS pages (1 per locale)
        // - Static pages (3 per locale: '', 'events', 'members')
        // - Event pages (1 per locale)
        // Total: 2 locales * (1 + 3 + 1) = 10 sites
        $this->assertCount(10, $capturedSites);

        // Verify some specific entries
        $this->assertContains([
            'loc' => 'https://example.com/en/about',
            'lastmod' => '2025-01-01',
            'prio' => 0.7,
        ], $capturedSites);

        $this->assertContains([
            'loc' => 'https://example.com/de/event/123',
            'lastmod' => '2025-02-01',
            'prio' => 0.6,
        ], $capturedSites);

        $this->assertContains([
            'loc' => 'https://example.com/en/events',
            'lastmod' => new DateTime()->format('Y-m-d'),
            'prio' => 0.9,
        ], $capturedSites);
    }
}
