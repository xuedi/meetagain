<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Event;
use App\Repository\EventRepository;
use App\Service\CmsService;
use App\Service\SitemapService;
use DateTime;
use DateTimeImmutable;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\MockObject\Stub;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class SitemapServiceTest extends TestCase
{
    private MockObject&Environment $environmentMock;
    private Stub&CmsService $cmsServiceStub;
    private Stub&EventRepository $eventRepositoryStub;
    private MockObject&ParameterBagInterface $parameterBagMock;
    private SitemapService $subject;

    protected function setUp(): void
    {
        $this->environmentMock = $this->createMock(Environment::class);
        $this->cmsServiceStub = $this->createStub(CmsService::class);
        $this->eventRepositoryStub = $this->createStub(EventRepository::class);
        $this->parameterBagMock = $this->createMock(ParameterBagInterface::class);

        $this->subject = new SitemapService(
            twig: $this->environmentMock,
            cms: $this->cmsServiceStub,
            events: $this->eventRepositoryStub,
            appParams: $this->parameterBagMock,
        );
    }

    public function testGetContentReturnsSitemapXmlResponse(): void
    {
        // Arrange: test data
        $host = 'example.com';
        $locales = ['en', 'de'];
        $renderedContent = '<?xml version="1.0" encoding="UTF-8"?><urlset></urlset>';

        // Arrange: stub CMS site
        $cmsSite = $this->createStub(\App\Entity\Cms::class);
        $cmsSite->method('getSlug')->willReturn('about');
        $cmsSite->method('getCreatedAt')->willReturn(new DateTimeImmutable('2025-01-01'));
        $this->cmsServiceStub->method('getSites')->willReturn([$cmsSite]);

        // Arrange: stub event
        $event = $this->createStub(Event::class);
        $event->method('getId')->willReturn(123);
        $event->method('getStart')->willReturn(new DateTime('2025-02-01'));
        $this->eventRepositoryStub->method('findAll')->willReturn([$event]);

        // Arrange: mock parameter bag to return locales
        $this->parameterBagMock
            ->expects($this->once())
            ->method('get')
            ->with('kernel.enabled_locales')
            ->willReturn($locales);

        // Arrange: capture sites array and mock Twig render
        $capturedSites = null;
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

        // Act: get sitemap content
        $response = $this->subject->getContent($host);

        // Assert: response has correct status, content type and content
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
        $this->assertSame('text/xml', $response->headers->get('Content-Type'));
        $this->assertSame($renderedContent, $response->getContent());

        // Assert: sites array contains expected entries
        // 2 locales * (1 CMS page + 3 static pages + 1 event) = 10 sites
        $this->assertNotNull($capturedSites);
        $this->assertCount(10, $capturedSites);

        // Assert: CMS page entry exists
        $this->assertContains([
            'loc' => 'https://example.com/en/about',
            'lastmod' => '2025-01-01',
            'prio' => 0.7,
        ], $capturedSites);

        // Assert: event page entry exists
        $this->assertContains([
            'loc' => 'https://example.com/de/event/123',
            'lastmod' => '2025-02-01',
            'prio' => 0.6,
        ], $capturedSites);

        // Assert: static page entry exists
        $this->assertContains([
            'loc' => 'https://example.com/en/events',
            'lastmod' => (new DateTime())->format('Y-m-d'),
            'prio' => 0.9,
        ], $capturedSites);
    }
}
