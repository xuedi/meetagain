<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Cms;
use App\Repository\CmsRepository;
use App\Repository\EventRepository;
use App\Service\CmsService;
use Doctrine\Common\Collections\ArrayCollection;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Response;
use Twig\Environment;

#[AllowMockObjectsWithoutExpectations]
class CmsServiceTest extends TestCase
{
    private MockObject|Environment $twigMock;
    private MockObject|CmsRepository $cmsRepoMock;
    private MockObject|EventRepository $eventRepoMock;
    private CmsService $subject;

    protected function setUp(): void
    {
        $this->twigMock = $this->createMock(Environment::class);
        $this->cmsRepoMock = $this->createMock(CmsRepository::class);
        $this->eventRepoMock = $this->createMock(EventRepository::class);

        $this->subject = new CmsService(
            twig: $this->twigMock,
            repo: $this->cmsRepoMock,
            eventRepo: $this->eventRepoMock,
        );
    }

    public function testCanCreateNotFoundPage(): void
    {
        $expectedCode = 404;
        $expectedContent = '404 page content';

        $this->twigMock
            ->expects($this->once())
            ->method('render')
            ->with('cms/404.html.twig')
            ->willReturn($expectedContent);

        $response = $this->subject->createNotFoundPage();

        $this->assertEquals($expectedContent, $response->getContent());
        $this->assertEquals($expectedCode, $response->getStatusCode());
    }

    public function testCanGetSites(): void
    {
        $this->cmsRepoMock->expects($this->once())->method('findAll');
        $this->subject->getSites();
    }

    public function testHandleReturnsNotFoundPageWhenCmsPageNotFound(): void
    {
        $locale = 'en';
        $slug = 'non-existent-page';
        $expectedCode = 404;
        $expectedContent = '404 page content';

        // Mock the repository to return null (page not found)
        $this->cmsRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'slug' => $slug,
                'published' => true,
            ])
            ->willReturn(null);

        // Mock the twig render for 404 page
        $this->twigMock
            ->expects($this->once())
            ->method('render')
            ->with('cms/404.html.twig', $this->anything())
            ->willReturn($expectedContent);

        $response = $this->subject->handle($locale, $slug, new Response());

        $this->assertEquals($expectedContent, $response->getContent());
        $this->assertEquals($expectedCode, $response->getStatusCode());
    }

    public function testHandleReturnsNoContentWhenPageHasNoContentInRequestedLanguage(): void
    {
        $locale = 'en';
        $slug = 'existing-page';
        $expectedCode = 204;
        $expectedContent = '204 page content';

        // Create a mock Cms entity
        $cmsMock = $this->createMock(Cms::class);

        // Mock the repository to return the mock Cms entity
        $this->cmsRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'slug' => $slug,
                'published' => true,
            ])
            ->willReturn($cmsMock);

        // Mock the getLanguageFilteredBlockJsonList method to return an empty collection
        $emptyCollection = new ArrayCollection();
        $cmsMock
            ->expects($this->once())
            ->method('getLanguageFilteredBlockJsonList')
            ->with($locale)
            ->willReturn($emptyCollection);

        // Mock the twig render for 204 page
        $this->twigMock
            ->expects($this->once())
            ->method('render')
            ->with('cms/204.html.twig', $this->anything())
            ->willReturn($expectedContent);

        $response = $this->subject->handle($locale, $slug, new Response());

        $this->assertEquals($expectedContent, $response->getContent());
        $this->assertEquals($expectedCode, $response->getStatusCode());
    }

    public function testHandleReturnsPageContentWhenPageHasContentInRequestedLanguage(): void
    {
        $locale = 'en';
        $slug = 'existing-page';
        $pageTitle = 'Page Title';
        $expectedCode = 200;
        $expectedContent = 'page content';
        $upcomingEvents = ['event1', 'event2'];

        // Create a mock Cms entity
        $cmsMock = $this->createMock(Cms::class);

        // Mock the repository to return the mock Cms entity
        $this->cmsRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with([
                'slug' => $slug,
                'published' => true,
            ])
            ->willReturn($cmsMock);

        // Create a non-empty collection of blocks
        $blocks = new ArrayCollection(['block1', 'block2']);

        // Mock the getLanguageFilteredBlockJsonList method to return the non-empty collection
        $cmsMock
            ->expects($this->once())
            ->method('getLanguageFilteredBlockJsonList')
            ->with($locale)
            ->willReturn($blocks);

        // Mock the getPageTitle method
        $cmsMock->expects($this->once())->method('getPageTitle')->with($locale)->willReturn($pageTitle);

        // Mock the eventRepo to return upcoming events
        $this->eventRepoMock
            ->expects($this->once())
            ->method('getUpcomingEvents')
            ->willReturn($upcomingEvents);

        // Mock the twig render for the page content
        $this->twigMock
            ->expects($this->once())
            ->method('render')
            ->with('cms/index.html.twig', [
                'title' => $pageTitle,
                'blocks' => $blocks,
                'events' => $upcomingEvents,
            ])
            ->willReturn($expectedContent);

        $response = $this->subject->handle($locale, $slug, new Response());

        $this->assertEquals($expectedContent, $response->getContent());
        $this->assertEquals($expectedCode, $response->getStatusCode());
    }
}
