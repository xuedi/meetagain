<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\PluginListCommand;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class PluginListCommandTest extends TestCase
{
    public function testEveryPluginIsListedWithItsState(): void
    {
        // Arrange
        $plugins = $this->createStub(PluginService::class);
        $plugins
            ->method('getAdminList')
            ->willReturn([
                ['key' => 'books', 'name' => 'Books', 'version' => '1.0', 'description' => '', 'system' => false, 'enabled' => true, 'requires' => []],
                ['key' => 'films', 'name' => 'Films', 'version' => '1.0', 'description' => '', 'system' => false, 'enabled' => false, 'requires' => ['books']],
                ['key' => 'pinned', 'name' => 'Pinned', 'version' => '1.5.0', 'description' => '', 'system' => true, 'enabled' => true, 'requires' => []],
            ]);
        $tester = new CommandTester(new PluginListCommand($plugins));

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertMatchesRegularExpression('/books\s+\|\s+Books\s+\|\s+1\.0\s+\|\s+on\s+\|/', $tester->getDisplay());
        static::assertMatchesRegularExpression('/films\s+\|\s+Films\s+\|\s+1\.0\s+\|\s+off\s+\|\s+books/', $tester->getDisplay());
        static::assertStringContainsString('always on', $tester->getDisplay());
    }

    public function testAnInstallWithoutPluginsSaysSo(): void
    {
        // Arrange
        $plugins = $this->createStub(PluginService::class);
        $plugins->method('getAdminList')->willReturn([]);
        $tester = new CommandTester(new PluginListCommand($plugins));

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('No plugins found', $tester->getDisplay());
    }
}
