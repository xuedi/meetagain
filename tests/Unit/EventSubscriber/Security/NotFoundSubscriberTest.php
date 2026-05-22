<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber\Security;

use App\Enum\SecurityEventType;
use App\EventSubscriber\Security\NotFoundSubscriber;
use App\Service\Security\SecurityService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class NotFoundSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsWiresExceptionAtPriority32(): void
    {
        $events = NotFoundSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::EXCEPTION, $events);
        static::assertSame([['onKernelException', 32]], $events[KernelEvents::EXCEPTION]);
    }

    public function testNotFoundExceptionIsRecorded(): void
    {
        // Arrange
        $securityService = $this->createMock(SecurityService::class);
        $securityService->expects($this->once())->method('event')->with(SecurityEventType::NotFound, static::isInstanceOf(Request::class));

        $subscriber = new NotFoundSubscriber($securityService);
        $event = $this->createEvent(new NotFoundHttpException('missing'));

        // Act
        $subscriber->onKernelException($event);
    }

    public function testUnrelatedExceptionsAreIgnored(): void
    {
        // Arrange
        $securityService = $this->createMock(SecurityService::class);
        $securityService->expects($this->never())->method('event');

        $subscriber = new NotFoundSubscriber($securityService);
        $event = $this->createEvent(new RuntimeException('unrelated'));

        // Act / Assert
        $subscriber->onKernelException($event);
    }

    private function createEvent(\Throwable $throwable): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $throwable);
    }
}
