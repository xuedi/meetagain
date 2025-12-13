<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Cms;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Service\CmsService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

class CmsServiceTest extends TestCase
{
    public function testCreateNotFoundPageReturns404Response(): void
    {
        // Arrange: mock Twig to render 404 template
        $expectedContent = '404 page content';

        $twigMock = $this->createMock(Environment::class);
        $twigMock
            ->expects($this->once())
            ->method('render')
            ->with('cms/404.html.twig', ['message' => "These aren't the droids you're looking for!"])
            ->willReturn($expectedContent);

        $subject = new CmsService(
            twig: $twigMock,
            repo: $this->createStub(CmsRepository::class),
            eventRepo: $this->createStub(EventRepository::class),
        );

        // Act: create not found page
        $response = $subject->createNotFoundPage();

        // Assert: returns 404 response with rendered content
        $this->assertSame($expectedContent, $response->getContent());
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testGetSitesReturnsAllCmsPages(): void
    {
        // Arrange: mock repository to return list of CMS pages
        $expectedSites = [
            $this->createStub(Cms::class),
            $this->createStub(Cms::class),
        ];

        $cmsRepoMock = $this->createMock(CmsRepository::class);
        $cmsRepoMock
            ->expects($this->once())
            ->method('findAll')
            ->willReturn($expectedSites);

        $subject = new CmsService(
            twig: $this->createStub(Environment::class),
            repo: $cmsRepoMock,
            eventRepo: $this->createStub(EventRepository::class),
        );

        // Act: get all sites
        $result = $subject->getSites();

        // Assert: returns array of CMS pages
        $this->assertSame($expectedSites, $result);
    }

    public function testHandleReturns404WhenPageNotFound(): void
    {
        // Arrange: mock repository to return null (page not found)
        $locale = 'en';
        $slug = 'non-existent-page';
        $expectedContent = '404 page content';

        $cmsRepoMock = $this->createMock(CmsRepository::class);
        $cmsRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => $slug, 'published' => true])
            ->willReturn(null);

        $twigMock = $this->createMock(Environment::class);
        $twigMock
            ->expects($this->once())
            ->method('render')
            ->with('cms/404.html.twig', $this->anything())
            ->willReturn($expectedContent);

        $subject = new CmsService(
            twig: $twigMock,
            repo: $cmsRepoMock,
            eventRepo: $this->createStub(EventRepository::class),
        );

        // Act: handle request for non-existent page
        $response = $subject->handle($locale, $slug, new Response());

        // Assert: returns 404 response
        $this->assertSame($expectedContent, $response->getContent());
        $this->assertSame(Response::HTTP_NOT_FOUND, $response->getStatusCode());
    }

    public function testHandleReturns204WhenPageHasNoContentInRequestedLanguage(): void
    {
        // Arrange: mock CMS page with no content blocks for requested locale
        $locale = 'en';
        $slug = 'existing-page';
        $expectedContent = '204 page content';

        $cmsMock = $this->createMock(Cms::class);
        $cmsMock
            ->expects($this->once())
            ->method('getLanguageFilteredBlockJsonList')
            ->with($locale)
            ->willReturn(new ArrayCollection());

        $cmsRepoMock = $this->createMock(CmsRepository::class);
        $cmsRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => $slug, 'published' => true])
            ->willReturn($cmsMock);

        $twigMock = $this->createMock(Environment::class);
        $twigMock
            ->expects($this->once())
            ->method('render')
            ->with('cms/204.html.twig', ['message' => 'page was found but is has no content in this language'])
            ->willReturn($expectedContent);

        $subject = new CmsService(
            twig: $twigMock,
            repo: $cmsRepoMock,
            eventRepo: $this->createStub(EventRepository::class),
        );

        // Act: handle request for page without content in requested language
        $response = $subject->handle($locale, $slug, new Response());

        // Assert: returns 204 No Content response
        $this->assertSame($expectedContent, $response->getContent());
        $this->assertSame(Response::HTTP_NO_CONTENT, $response->getStatusCode());
    }

    public function testHandleReturns200WithContentWhenPageExists(): void
    {
        // Arrange: mock CMS page with content blocks
        $locale = 'en';
        $slug = 'existing-page';
        $pageTitle = 'Page Title';
        $expectedContent = 'rendered page content';
        $upcomingEvents = ['event1', 'event2'];
        $blocks = new ArrayCollection(['block1', 'block2']);

        $cmsMock = $this->createMock(Cms::class);
        $cmsMock
            ->expects($this->once())
            ->method('getLanguageFilteredBlockJsonList')
            ->with($locale)
            ->willReturn($blocks);
        $cmsMock
            ->expects($this->once())
            ->method('getPageTitle')
            ->with($locale)
            ->willReturn($pageTitle);

        $cmsRepoMock = $this->createMock(CmsRepository::class);
        $cmsRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['slug' => $slug, 'published' => true])
            ->willReturn($cmsMock);

        $eventRepoMock = $this->createMock(EventRepository::class);
        $eventRepoMock
            ->expects($this->once())
            ->method('getUpcomingEvents')
            ->willReturn($upcomingEvents);

        $twigMock = $this->createMock(Environment::class);
        $twigMock
            ->expects($this->once())
            ->method('render')
            ->with('cms/index.html.twig', [
                'title' => $pageTitle,
                'blocks' => $blocks,
                'events' => $upcomingEvents,
            ])
            ->willReturn($expectedContent);

        $subject = new CmsService(
            twig: $twigMock,
            repo: $cmsRepoMock,
            eventRepo: $eventRepoMock,
        );

        // Act: handle request for page with content
        $response = $subject->handle($locale, $slug, new Response());

        // Assert: returns 200 OK response with rendered content
        $this->assertSame($expectedContent, $response->getContent());
        $this->assertSame(Response::HTTP_OK, $response->getStatusCode());
    }

    public function testHandleUsesDefaultTitleWhenPageTitleIsNull(): void
    {
        // Arrange: mock CMS page with null title
        $locale = 'en';
        $slug = 'page-without-title';
        $expectedContent = 'rendered page content';
        $blocks = new ArrayCollection(['block1']);

        // Arrange: stub CMS entity to return blocks and null title
        $cmsStub = $this->createStub(Cms::class);
        $cmsStub->method('getLanguageFilteredBlockJsonList')->willReturn($blocks);
        $cmsStub->method('getPageTitle')->willReturn(null);

        // Arrange: stub repository to return the CMS entity
        $cmsRepoStub = $this->createStub(CmsRepository::class);
        $cmsRepoStub->method('findOneBy')->willReturn($cmsStub);

        // Arrange: stub event repository to return empty events
        $eventRepoStub = $this->createStub(EventRepository::class);
        $eventRepoStub->method('getUpcomingEvents')->willReturn([]);

        // Arrange: mock Twig to verify default title is used
        $twigMock = $this->createMock(Environment::class);
        $twigMock
            ->expects($this->once())
            ->method('render')
            ->with('cms/index.html.twig', [
                'title' => 'No Title set',
                'blocks' => $blocks,
                'events' => [],
            ])
            ->willReturn($expectedContent);

        $subject = new CmsService(
            twig: $twigMock,
            repo: $cmsRepoStub,
            eventRepo: $eventRepoStub,
        );

        // Act: handle request for page without title
        $response = $subject->handle($locale, $slug, new Response());

        // Assert: uses default title "No Title set"
        $this->assertSame($expectedContent, $response->getContent());
    }
}
