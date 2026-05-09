<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber\Security;

use App\EventSubscriber\Security\AccessDeniedSubscriber;
use App\Service\Security\AccessDeniedLogger;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AccessDeniedSubscriberTest extends TestCase
{
    private function createSubscriber(?AccessDeniedLogger $logger = null): AccessDeniedSubscriber
    {
        return new AccessDeniedSubscriber(
            $logger ?? $this->createStub(AccessDeniedLogger::class),
        );
    }

    public function testGetSubscribedEventsReturnsKernelException(): void
    {
        // Arrange + Act
        $events = AccessDeniedSubscriber::getSubscribedEvents();

        // Assert
        static::assertArrayHasKey(KernelEvents::EXCEPTION, $events);
        static::assertEquals([['onKernelException', 16]], $events[KernelEvents::EXCEPTION]);
    }

    public function testLogsHttpAccessDeniedException(): void
    {
        // Arrange
        $logger = $this->createMock(AccessDeniedLogger::class);
        $logger->expects($this->once())->method('log')->with(
            static::isInstanceOf(AccessDeniedHttpException::class),
            static::isInstanceOf(Request::class),
            true,
        );
        $subscriber = $this->createSubscriber(logger: $logger);

        $request = Request::create('/admin/secret');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new AccessDeniedHttpException('denied'),
        );

        // Act
        $subscriber->onKernelException($event);

        // Assert
        static::assertNull($event->getResponse(), 'Subscriber must not set a response');
    }

    public function testLogsCoreAccessDeniedException(): void
    {
        // Arrange
        $logger = $this->createMock(AccessDeniedLogger::class);
        $logger->expects($this->once())->method('log')->with(
            static::isInstanceOf(AccessDeniedException::class),
            static::isInstanceOf(Request::class),
            false,
        );
        $subscriber = $this->createSubscriber(logger: $logger);

        $request = Request::create('/admin/secret');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new AccessDeniedException('denied'),
        );

        // Act
        $subscriber->onKernelException($event);

        // Assert
        static::assertNull($event->getResponse());
    }

    public function testIgnoresUnrelatedThrowables(): void
    {
        // Arrange
        $logger = $this->createMock(AccessDeniedLogger::class);
        $logger->expects($this->never())->method('log');
        $subscriber = $this->createSubscriber(logger: $logger);

        $request = Request::create('/');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $event = new ExceptionEvent(
            $kernel,
            $request,
            HttpKernelInterface::MAIN_REQUEST,
            new Exception('unrelated'),
        );

        // Act
        $subscriber->onKernelException($event);

        // Assert
        static::assertNull($event->getResponse());
    }
}
