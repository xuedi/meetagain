<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\PluginCommand;
use App\Service\Config\PluginService;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

#[AllowMockObjectsWithoutExpectations]
class PluginCommandTest extends TestCase
{
    private PluginService $pluginService;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->pluginService = $this->createMock(PluginService::class);
        $command = new PluginCommand($this->pluginService);
        $this->commandTester = new CommandTester($command);
    }

    public function testNoArgumentsReturnsSuccess(): void
    {
        // Act: execute command without arguments
        $exitCode = $this->commandTester->execute([]);

        // Assert: returns success
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testInvalidActionReturnsFailure(): void
    {
        // Act: execute command with invalid action
        $exitCode = $this->commandTester->execute([
            'action' => 'invalid',
            'plugin' => 'demo',
        ]);

        // Assert: returns failure
        static::assertSame(Command::FAILURE, $exitCode);
    }

    public function testEnableWithoutPluginArgumentIsNoOp(): void
    {
        // Arrange: expect no service calls when plugin is missing
        $this->pluginService->expects($this->never())->method('install');
        $this->pluginService->expects($this->never())->method('enable');

        // Act: execute command with action but no plugin
        $exitCode = $this->commandTester->execute([
            'action' => 'enable',
        ]);

        // Assert: returns success without calling service
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testEnableWithEmptyPluginArgumentIsNoOp(): void
    {
        // Arrange: expect no service calls when plugin is empty string
        $this->pluginService->expects($this->never())->method('install');
        $this->pluginService->expects($this->never())->method('enable');

        // Act: execute command with empty plugin
        $exitCode = $this->commandTester->execute([
            'action' => 'enable',
            'plugin' => '',
        ]);

        // Assert: returns success without calling service
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testEnablePluginCallsService(): void
    {
        // Arrange: expect install and enable to be called
        $this->pluginService
            ->expects($this->once())
            ->method('install')
            ->with('demo');

        $this->pluginService
            ->expects($this->once())
            ->method('enable')
            ->with('demo');

        // Act: enable a plugin
        $exitCode = $this->commandTester->execute([
            'action' => 'enable',
            'plugin' => 'demo',
        ]);

        // Assert: returns success
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testDisablePluginCallsService(): void
    {
        // Arrange: expect disable to be called
        $this->pluginService
            ->expects($this->once())
            ->method('disable')
            ->with('demo');

        // Act: disable a plugin
        $exitCode = $this->commandTester->execute([
            'action' => 'disable',
            'plugin' => 'demo',
        ]);

        // Assert: returns success
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testDisableAllClearsConfig(): void
    {
        // Arrange: expect setPluginConfig to be called with empty array
        $this->pluginService
            ->expects($this->once())
            ->method('setPluginConfig')
            ->with([]);

        // Act: disable all plugins
        $exitCode = $this->commandTester->execute([
            'action' => 'disable',
            'plugin' => 'all',
        ]);

        // Assert: returns success
        static::assertSame(Command::SUCCESS, $exitCode);
    }
}
