<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\Command\EchoCommand;
use App\Service\CommandService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;

class CommandServiceTest extends TestCase
{
    private function createService(): CommandService
    {
        // Arrange: stub event dispatcher and container
        $eventDispatcherStub = $this->createStub(EventDispatcher::class);

        $containerStub = $this->createStub(ContainerInterface::class);
        $containerStub->method('get')->with('event_dispatcher')->willReturn($eventDispatcherStub);

        $kernelStub = $this->createStub(KernelInterface::class);
        $kernelStub->method('getContainer')->willReturn($containerStub);

        return new CommandService(kernel: $kernelStub);
    }

    public function testExecuteCommandReturnsOutput(): void
    {
        // Arrange: create service with default stubs
        $service = $this->createService();

        // Act & Assert: execute echo command returns output
        $this->assertNotEmpty($service->execute(new EchoCommand('test')));
    }

    public function testClearCacheExecutesWithoutError(): void
    {
        // Arrange: create service with default stubs
        $service = $this->createService();

        // Act & Assert: clear cache runs without throwing
        $service->clearCache();
        $this->assertTrue(true);
    }

    public function testExecuteMigrationsExecutesWithoutError(): void
    {
        // Arrange: create service with default stubs
        $service = $this->createService();

        // Act & Assert: execute migrations runs without throwing
        $service->executeMigrations();
        $this->assertTrue(true);
    }
}
