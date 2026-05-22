<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber\Security;

use App\Enum\SecurityEventType;
use App\EventSubscriber\Security\AccessDeniedSubscriber;
use App\Service\Security\SecurityService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\AccessDeniedHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;

class AccessDeniedSubscriberTest extends TestCase
{
    public function testGetSubscribedEventsWiresExceptionAtPriority16(): void
    {
        $events = AccessDeniedSubscriber::getSubscribedEvents();

        static::assertArrayHasKey(KernelEvents::EXCEPTION, $events);
        static::assertSame([['onKernelException', 16]], $events[KernelEvents::EXCEPTION]);
    }

    public function testHttpAccessDeniedIsRecordedWithFlag(): void
    {
        // Arrange
        $captured = [];
        $securityService = $this->createMock(SecurityService::class);
        $securityService
            ->expects($this->once())
            ->method('event')
            ->willReturnCallback(static function (...$args) use (&$captured): void {
                $captured = $args;
            });

        $subscriber = new AccessDeniedSubscriber($securityService);
        $event = $this->createEvent(new AccessDeniedHttpException('forbidden'));

        // Act
        $subscriber->onKernelException($event);

        // Assert
        static::assertSame(SecurityEventType::AccessDenied, $captured[0]);
        static::assertTrue($captured[2]['isHttpAccessDenied']);
        static::assertArrayHasKey('reason', $captured[2]);
    }

    public function testCoreAccessDeniedIsRecordedWithoutFlag(): void
    {
        // Arrange
        $captured = [];
        $securityService = $this->createMock(SecurityService::class);
        $securityService
            ->expects($this->once())
            ->method('event')
            ->willReturnCallback(static function (...$args) use (&$captured): void {
                $captured = $args;
            });

        $subscriber = new AccessDeniedSubscriber($securityService);
        $event = $this->createEvent(new AccessDeniedException('nope'));

        // Act
        $subscriber->onKernelException($event);

        // Assert
        static::assertFalse($captured[2]['isHttpAccessDenied']);
    }

    public function testUnrelatedExceptionsAreIgnored(): void
    {
        // Arrange
        $securityService = $this->createMock(SecurityService::class);
        $securityService->expects($this->never())->method('event');

        $subscriber = new AccessDeniedSubscriber($securityService);
        $event = $this->createEvent(new RuntimeException('unrelated'));

        // Act
        $subscriber->onKernelException($event);

        // Assert
        static::assertTrue(true);
    }

    private function createEvent(\Throwable $throwable): ExceptionEvent
    {
        return new ExceptionEvent($this->createStub(HttpKernelInterface::class), Request::create('/'), HttpKernelInterface::MAIN_REQUEST, $throwable);
    }
}
