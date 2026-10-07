<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Service\Admin\CommandService;
use App\Service\Command\CommandInterface;
use App\Service\Command\EchoCommand;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\DependencyInjection\ContainerInterface;
use Symfony\Component\EventDispatcher\EventDispatcher;
use Symfony\Component\HttpKernel\KernelInterface;

class CommandServiceTest extends TestCase
{
    private function createService(string $projectDir = ''): CommandService
    {
        // Arrange
        $eventDispatcherStub = $this->createStub(EventDispatcher::class);

        $containerStub = $this->createStub(ContainerInterface::class);
        $containerStub->method('get')->willReturn($eventDispatcherStub);

        $kernelStub = $this->createStub(KernelInterface::class);
        $kernelStub->method('getContainer')->willReturn($containerStub);
        $kernelStub->method('getProjectDir')->willReturn($projectDir);
        $kernelStub->method('getEnvironment')->willReturn('test');

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

    public function testExecuteRunsTheCommandNameWhenParametersOmitIt(): void
    {
        // Arrange
        $service = $this->createService();
        $command = new class implements CommandInterface {
            public function getCommand(): string
            {
                return 'help';
            }

            public function getParameter(): array
            {
                return [];
            }
        };

        // Act
        $output = $service->execute($command);

        // Assert
        static::assertStringContainsString('command displays help', $output);
        static::assertStringNotContainsString('Available commands', $output);
    }

    public function testRebuildThemeRunsTheFullRebuildChain(): void
    {
        // Arrange
        $service = $this->createService();

        // Act
        $output = $service->rebuildTheme();

        // Assert
        static::assertMatchesRegularExpression('/&quot;sass&quot;.*&quot;asset-map&quot;.*&quot;cache&quot;.*&quot;cache:pool&quot;/s', $output);
    }

    public function testSubprocessMigrationsFailLoudlyWhenTheProjectDirIsMissing(): void
    {
        // Arrange
        $service = $this->createService(projectDir: '/nonexistent/meetagain-project');

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('/nonexistent/meetagain-project');

        // Act
        $service->executeSubprocessMigrations();
    }
}
