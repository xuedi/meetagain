<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber;

use App\Entity\NotFoundLog;
use App\EventSubscriber\NotFoundSubscriber;
use App\Service\CmsService;
use App\Service\SitemapService;
use Doctrine\ORM\EntityManagerInterface;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\RouterInterface;

class NotFoundSubscriberTest extends TestCase
{
    private function createSubscriber(
        ?CmsService $cms = null,
        ?SitemapService $sitemap = null,
        ?RouterInterface $router = null,
        ?EntityManagerInterface $em = null,
    ): NotFoundSubscriber {
        return new NotFoundSubscriber(
            $cms ?? $this->createStub(CmsService::class),
            $sitemap ?? $this->createStub(SitemapService::class),
            $router ?? $this->createStub(RouterInterface::class),
            $em ?? $this->createStub(EntityManagerInterface::class),
        );
    }

    public function testGetSubscribedEventsReturnsKernelException(): void
    {
        $events = NotFoundSubscriber::getSubscribedEvents();

        $this->assertArrayHasKey(KernelEvents::EXCEPTION, $events);
        $this->assertEquals([['onKernelException', 32]], $events[KernelEvents::EXCEPTION]);
    }

    public function testOnKernelExceptionIgnoresNonNotFoundExceptions(): void
    {
        $cms = $this->createMock(CmsService::class);
        $cms->expects($this->never())->method('createNotFoundPage');

        $subscriber = $this->createSubscriber(cms: $cms);

        $request = Request::create('/some-path');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $exception = new Exception('Some other error');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $subscriber->onKernelException($event);

        $this->assertNull($event->getResponse());
    }

    public function testOnKernelExceptionHandlesSitemapXml(): void
    {
        $sitemapContent = '<?xml version="1.0"?><urlset></urlset>';
        $sitemapResponse = new Response($sitemapContent, Response::HTTP_OK);

        $sitemap = $this->createMock(SitemapService::class);
        $sitemap->expects($this->once())
            ->method('getContent')
            ->with('dragon-descendants.de')
            ->willReturn($sitemapResponse);

        $cms = $this->createMock(CmsService::class);
        $cms->expects($this->never())->method('createNotFoundPage');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $subscriber = $this->createSubscriber(cms: $cms, sitemap: $sitemap, em: $em);

        $request = Request::create('/sitemap.xml');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $exception = new NotFoundHttpException('Not found');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $subscriber->onKernelException($event);

        $this->assertSame($sitemapResponse, $event->getResponse());
    }

    public function testOnKernelExceptionCreatesNotFoundPageAndLogsTo404Log(): void
    {
        $notFoundResponse = new Response('Not Found', Response::HTTP_NOT_FOUND);

        $cms = $this->createMock(CmsService::class);
        $cms->expects($this->once())
            ->method('createNotFoundPage')
            ->willReturn($notFoundResponse);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())
            ->method('persist')
            ->with($this->isInstanceOf(NotFoundLog::class));
        $em->expects($this->once())->method('flush');

        $subscriber = $this->createSubscriber(cms: $cms, em: $em);

        $request = Request::create('/unknown-page');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $exception = new NotFoundHttpException('Not found');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $subscriber->onKernelException($event);

        $this->assertSame($notFoundResponse, $event->getResponse());
    }

    public function testOnKernelExceptionDoesNotLogWhenResponseIsOk(): void
    {
        $okResponse = new Response('Found via redirect', Response::HTTP_OK);

        $cms = $this->createMock(CmsService::class);
        $cms->expects($this->once())
            ->method('createNotFoundPage')
            ->willReturn($okResponse);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $subscriber = $this->createSubscriber(cms: $cms, em: $em);

        $request = Request::create('/redirect-to-valid-page');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $exception = new NotFoundHttpException('Not found');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $subscriber->onKernelException($event);
    }

    public function testOnKernelExceptionStopsPropagation(): void
    {
        $notFoundResponse = new Response('Not Found', Response::HTTP_NOT_FOUND);

        $cms = $this->createStub(CmsService::class);
        $cms->method('createNotFoundPage')->willReturn($notFoundResponse);

        $subscriber = $this->createSubscriber(cms: $cms);

        $request = Request::create('/unknown-page');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $exception = new NotFoundHttpException('Not found');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        $subscriber->onKernelException($event);

        $this->assertTrue($event->isPropagationStopped());
    }
}
