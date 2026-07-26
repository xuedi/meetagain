<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\Admin\CommandService;
use App\Service\Command\EchoCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;

class CommandServiceTest extends TestCase
{
    private function createService(): CommandService
    {
        // Arrange
        $eventDispatcherStub = $this->createStub(EventDispatcher::class);

        $containerStub = $this->createStub(ContainerInterface::class);
        $containerStub->method('get')->willReturn($eventDispatcherStub);

        $kernelStub = $this->createStub(KernelInterface::class);
        $kernelStub->method('getContainer')->willReturn($containerStub);

        return new CommandService(kernel: $kernelStub);
    }

    public function testExecuteCommandReturnsOutput(): void
    {
        // Arrange
        $service = $this->createService();

        // Act & Assert
        static::assertNotEmpty($service->execute(new EchoCommand('test')));
    }

    public function testClearCacheExecutesWithoutError(): void
    {
        // Arrange
        $service = $this->createService();

        // Act & Assert
        $service->clearCache();
        static::assertTrue(true);
    }

    public function testExecuteMigrationsExecutesWithoutError(): void
    {
        // Arrange
        $service = $this->createService();

        // Act & Assert
        $service->executeMigrations();
        static::assertTrue(true);
    }
}
