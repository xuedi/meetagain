<?php declare(strict_types=1);

namespace Tests\Unit\EventSubscriber;

use App\EventSubscriber\PluginRouteGuard;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\HttpKernel\HttpKernelInterface;

class PluginRouteGuardTest extends TestCase
{
    public function testARouteOfASwitchedOffPluginIsNotFound(): void
    {
        // Arrange
        $guard = new PluginRouteGuard($this->plugins(enabled: false));

        // Assert
        $this->expectException(NotFoundHttpException::class);

        // Act
        $guard->onKernelRequest($this->event(['_plugin' => 'books']));
    }

    public function testARouteOfASwitchedOnPluginPasses(): void
    {
        // Arrange
        $guard = new PluginRouteGuard($this->plugins(enabled: true));
        $event = $this->event(['_plugin' => 'books']);

        // Act
        $guard->onKernelRequest($event);

        // Assert
        static::assertFalse($event->hasResponse());
    }

    public function testACoreRouteIsNeverAskedAbout(): void
    {
        // Arrange
        $plugins = $this->createMock(PluginService::class);
        $plugins->expects($this->never())->method('isEnabled');
        $guard = new PluginRouteGuard($plugins);

        // Act
        $guard->onKernelRequest($this->event([]));
    }

    private function plugins(bool $enabled): PluginService
    {
        $plugins = $this->createStub(PluginService::class);
        $plugins->method('isEnabled')->willReturn($enabled);

        return $plugins;
    }

    /** @param array<string, string> $attributes */
    private function event(array $attributes): RequestEvent
    {
        return new RequestEvent($this->createStub(HttpKernelInterface::class), new Request(attributes: $attributes), HttpKernelInterface::MAIN_REQUEST);
    }
}
