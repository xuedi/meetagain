<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber\Security;

use App\EventSubscriber\Security\NotFoundSubscriber;
use App\Service\Security\NotFoundLogger;
use Exception;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;

class NotFoundSubscriberTest extends TestCase
{
    private function createSubscriber(?NotFoundLogger $logger = null): NotFoundSubscriber
    {
        return new NotFoundSubscriber(
            $logger ?? $this->createStub(NotFoundLogger::class),
        );
    }

    public function testGetSubscribedEventsReturnsKernelException(): void
    {
        // Arrange + Act
        $events = NotFoundSubscriber::getSubscribedEvents();

        // Assert
        static::assertArrayHasKey(KernelEvents::EXCEPTION, $events);
        static::assertEquals([['onKernelException', 32]], $events[KernelEvents::EXCEPTION]);
    }

    public function testOnKernelExceptionIgnoresNonNotFoundExceptions(): void
    {
        // Arrange
        $logger = $this->createMock(NotFoundLogger::class);
        $logger->expects($this->never())->method('log');

        $subscriber = $this->createSubscriber(logger: $logger);

        $request = Request::create('/some-path');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $exception = new Exception('Some other error');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        // Act
        $subscriber->onKernelException($event);

        // Assert
        static::assertNull($event->getResponse());
    }

    public function testOnKernelExceptionLogsAndDoesNotSetResponse(): void
    {
        // Arrange
        $logger = $this->createMock(NotFoundLogger::class);
        $logger->expects($this->once())->method('log')->with(static::isInstanceOf(Request::class));

        $subscriber = $this->createSubscriber(logger: $logger);

        $request = Request::create('/unknown-page');
        $request->server->set('REMOTE_ADDR', '192.168.1.1');
        $kernel = $this->createStub(HttpKernelInterface::class);
        $exception = new NotFoundHttpException('Not found');

        $event = new ExceptionEvent($kernel, $request, HttpKernelInterface::MAIN_REQUEST, $exception);

        // Act
        $subscriber->onKernelException($event);

        // Assert
        static::assertNull($event->getResponse(), 'Subscriber must let ErrorController render');
        static::assertFalse($event->isPropagationStopped(), 'Subscriber must not stop propagation');
    }
}
