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
        // Act
        $exitCode = $this->commandTester->execute([]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testInvalidActionReturnsFailure(): void
    {
        // Act
        $exitCode = $this->commandTester->execute([
            'action' => 'invalid',
            'plugins' => ['demo'],
        ]);

        // Assert
        static::assertSame(Command::FAILURE, $exitCode);
    }

    public function testEnableWithoutPluginArgumentIsNoOp(): void
    {
        // Arrange
        $this->pluginService->expects($this->never())->method('install');
        $this->pluginService->expects($this->never())->method('enable');

        // Act
        $exitCode = $this->commandTester->execute([
            'action' => 'enable',
        ]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testEnableWithEmptyPluginArgumentIsNoOp(): void
    {
        // Arrange
        $this->pluginService->expects($this->never())->method('install');
        $this->pluginService->expects($this->never())->method('enable');

        // Act
        $exitCode = $this->commandTester->execute([
            'action' => 'enable',
            'plugins' => [''],
        ]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testEnablePluginCallsService(): void
    {
        // Arrange
        $this->pluginService->expects($this->once())->method('install')->with('demo');

        $this->pluginService->expects($this->once())->method('enable')->with('demo');

        // Act
        $exitCode = $this->commandTester->execute([
            'action' => 'enable',
            'plugins' => ['demo'],
        ]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testEnablingSeveralPluginsInstallsAndEnablesEachInTurn(): void
    {
        // Arrange
        $enabled = [];
        $this->pluginService->method('enable')->willReturnCallback(static function (string $key) use (&$enabled): void {
            $enabled[] = $key;
        });

        // Act
        $exitCode = $this->commandTester->execute([
            'action' => 'enable',
            'plugins' => ['books', 'wishlist'],
        ]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertSame(['books', 'wishlist'], $enabled);
    }

    public function testDisablePluginCallsService(): void
    {
        // Arrange
        $this->pluginService->expects($this->once())->method('disable')->with('demo');

        // Act
        $exitCode = $this->commandTester->execute([
            'action' => 'disable',
            'plugins' => ['demo'],
        ]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testDisableAllClearsConfig(): void
    {
        // Arrange
        $this->pluginService->expects($this->once())->method('setPluginConfig')->with([]);

        // Act
        $exitCode = $this->commandTester->execute([
            'action' => 'disable',
            'plugins' => ['all'],
        ]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
    }
}
