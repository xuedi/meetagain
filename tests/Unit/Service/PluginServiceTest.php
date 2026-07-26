<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\ExtendedFilesystem;
use App\Filter\Plugin\PluginListFilterInterface;
use App\Service\Admin\CommandService;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;

class PluginServiceTest extends TestCase
{
    private string $tempDir;
    private string $configFile;
    private string $envConfigFile;

    protected function setUp(): void
    {
        // Arrange
        $this->tempDir = sys_get_temp_dir() . '/plugin_service_test_' . uniqid();
        mkdir($this->tempDir, 0o777, true);

        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0o777, true);

        $this->configFile = $configDir . '/plugins.php';
        $this->envConfigFile = $configDir . '/plugins_test.php';
        file_put_contents($this->envConfigFile, '<?php declare(strict_types=1); return ["plugin1" => true, "plugin2" => false];');
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->tempDir);
    }

    public function testGetAdminListReturnsPluginManifestData(): void
    {
        // Arrange
        $pluginDir = $this->tempDir . '/plugins';
        mkdir($pluginDir, 0o777, true);
        mkdir($pluginDir . '/plugin1', 0o777, true);
        mkdir($pluginDir . '/plugin2', 0o777, true);

        file_put_contents($pluginDir . '/plugin1/manifest.json', json_encode([
            'name' => 'Plugin 1',
            'version' => '1.0.0',
            'description' => 'Test plugin 1',
        ]));
        file_put_contents($pluginDir . '/plugin2/manifest.json', json_encode([
            'name' => 'Plugin 2',
            'version' => '2.0.0',
            'description' => 'Test plugin 2',
        ]));

        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock
            ->method('getRealPath')
            ->willReturnCallback(fn($path) => match ($path) {
                $this->tempDir . '/plugins' => $pluginDir,
                $this->tempDir . '/config' => $this->tempDir . '/config',
                default => false,
            });
        $fsMock
            ->expects($this->once())
            ->method('glob')
            ->with($pluginDir . '/*', GLOB_ONLYDIR)
            ->willReturn([$pluginDir . '/plugin1', $pluginDir . '/plugin2']);
        $fsMock->method('exists')->willReturnCallback(static fn($path) => is_dir($path) || file_exists($path));
        $fsMock->method('fileExists')->willReturnCallback(file_exists(...));
        $fsMock->method('getFileContents')->willReturnCallback(file_get_contents(...));

        $subject = new PluginService($this->createStub(CommandService::class), $fsMock, $this->tempDir, 'test');

        // Act
        $result = $subject->getAdminList();

        // Assert
        static::assertCount(2, $result);
        static::assertSame('Plugin 1', $result[0]['name']);
        static::assertSame('1.0.0', $result[0]['version']);
        static::assertSame('Test plugin 1', $result[0]['description']);
        static::assertSame('Plugin 2', $result[1]['name']);
        static::assertSame('2.0.0', $result[1]['version']);
    }

    public function testGetAdminListReturnsEmptyArrayWhenPluginDirNotFound(): void
    {
        // Arrange
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('exists')->willReturn(false);
        $fsMock->expects($this->never())->method('glob');

        $subject = new PluginService($this->createStub(CommandService::class), $fsMock, $this->tempDir, 'test');

        // Act
        $result = $subject->getAdminList();

        // Assert
        static::assertSame([], $result);
    }

    public function testGetActiveListReturnsOnlyEnabledPlugins(): void
    {
        // Arrange
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(file_exists(...));

        $subject = new PluginService($this->createStub(CommandService::class), $fsStub, $this->tempDir, 'test');

        // Act
        $result = $subject->getActiveList();

        // Assert
        static::assertContains('plugin1', $result);
        static::assertNotContains('plugin2', $result);
    }

    public function testGetGloballyActiveListIgnoresFilters(): void
    {
        // Arrange
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(file_exists(...));

        $filterStub = $this->createStub(PluginListFilterInterface::class);
        $filterStub->method('filterActivePlugins')->willReturn([]);

        $subject = new PluginService($this->createStub(CommandService::class), $fsStub, $this->tempDir, 'test', [$filterStub]);

        // Act
        $result = $subject->getGloballyActiveList();

        // Assert
        static::assertContains('plugin1', $result);
    }

    public function testGetActiveListAppliesFilterAndIntersects(): void
    {
        // Arrange
        $pluginDir = $this->tempDir . '/plugins';
        mkdir($pluginDir . '/plugin1', 0o777, true);
        file_put_contents($pluginDir . '/plugin1/manifest.json', json_encode([
            'name' => 'Plugin 1',
            'description' => 'Group-activatable plugin',
        ]));

        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(file_exists(...));
        $fsStub->method('exists')->willReturnCallback(is_dir(...));
        $fsStub->method('glob')->willReturn([$pluginDir . '/plugin1']);
        $fsStub->method('getFileContents')->willReturnCallback(file_get_contents(...));

        $filter = $this->createMock(PluginListFilterInterface::class);
        $filter->expects($this->once())->method('filterActivePlugins')->with(['plugin1'])->willReturn([]);

        $subject = new PluginService($this->createStub(CommandService::class), $fsStub, $this->tempDir, 'test', [$filter]);

        // Act
        $result = $subject->getActiveList();

        // Assert
        static::assertNotContains('plugin1', $result);
        static::assertContains('core_navigation', $result);
    }

    public function testGetActiveListReturnsGlobalListWhenFilterReturnsNull(): void
    {
        // Arrange
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(file_exists(...));

        $filterStub = $this->createStub(PluginListFilterInterface::class);
        $filterStub->method('filterActivePlugins')->willReturn(null);

        $subject = new PluginService($this->createStub(CommandService::class), $fsStub, $this->tempDir, 'test', [$filterStub]);

        // Act
        $result = $subject->getActiveList();

        // Assert
        static::assertContains('plugin1', $result);
        static::assertNotContains('plugin2', $result);
    }

    public function testGetActivatableByGroupListExcludesGroupActivatableFalsePlugins(): void
    {
        // Arrange
        $pluginDir = $this->tempDir . '/plugins';
        mkdir($pluginDir, 0o777, true);
        mkdir($pluginDir . '/plugin1', 0o777, true);
        mkdir($pluginDir . '/hidden_plugin', 0o777, true);

        file_put_contents($pluginDir . '/plugin1/manifest.json', json_encode([
            'name' => 'Plugin 1',
            'description' => 'Regular plugin',
        ]));
        file_put_contents($pluginDir . '/hidden_plugin/manifest.json', json_encode([
            'name' => 'Hidden Plugin',
            'description' => 'Infrastructure only',
            'group_activatable' => false,
        ]));

        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('exists')->willReturn(true);
        $fsStub->method('glob')->willReturn([$pluginDir . '/plugin1', $pluginDir . '/hidden_plugin']);
        $fsStub->method('fileExists')->willReturnCallback(file_exists(...));
        $fsStub->method('getFileContents')->willReturnCallback(file_get_contents(...));

        $subject = new PluginService($this->createStub(CommandService::class), $fsStub, $this->tempDir, 'test');

        // Act
        $result = $subject->getActivatableByGroupList();

        // Assert
        $keys = array_column($result, 'key');
        static::assertContains('plugin1', $keys);
        static::assertNotContains('hidden_plugin', $keys);
    }

    public function testInstallAddsPluginToConfigAsDisabled(): void
    {
        // Arrange
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(file_exists(...));
        $fsStub
            ->method('putFileContents')
            ->willReturnCallback(static function ($path, $content) {
                file_put_contents($path, $content);
                return true;
            });

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->once())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir, 'test');

        // Act
        $subject->install('test-plugin');

        // Assert
        $config = include $this->configFile;
        static::assertArrayHasKey('test-plugin', $config);
        static::assertFalse($config['test-plugin']);
    }

    public function testInstallSkipsIfPluginAlreadyInstalled(): void
    {
        // Arrange
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(file_exists(...));

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir, 'test');

        // Act
        $subject->install('plugin1');

        // Assert
        $config = include $this->envConfigFile;
        static::assertTrue($config['plugin1']);
    }

    public function testUninstallRemovesPluginFromConfig(): void
    {
        // Arrange
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(file_exists(...));
        $fsStub
            ->method('putFileContents')
            ->willReturnCallback(static function ($path, $content) {
                file_put_contents($path, $content);
                return true;
            });

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->once())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir, 'test');

        // Act
        $subject->uninstall('plugin1');

        // Assert
        $config = include $this->configFile;
        static::assertArrayNotHasKey('plugin1', $config);
    }

    public function testUninstallSkipsIfPluginNotInstalled(): void
    {
        // Arrange
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('fileExists')->willReturnCallback(file_exists(...));
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir, 'test');

        // Act
        $subject->uninstall('non-existent-plugin');

        // Assert
        $config = include $this->envConfigFile;
        static::assertArrayNotHasKey('non-existent-plugin', $config);
    }

    public function testEnableSetsPluginToTrue(): void
    {
        // Arrange
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(file_exists(...));
        $fsStub
            ->method('putFileContents')
            ->willReturnCallback(static function ($path, $content) {
                file_put_contents($path, $content);
                return true;
            });

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->once())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir, 'test');

        // Act
        $subject->enable('plugin2');

        // Assert
        $config = include $this->configFile;
        static::assertTrue($config['plugin2']);
    }

    public function testEnableSkipsIfPluginNotInstalled(): void
    {
        // Arrange
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('fileExists')->willReturnCallback(file_exists(...));
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir, 'test');

        // Act
        $subject->enable('non-existent-plugin');

        // Assert
        static::assertTrue(true);
    }

    public function testEnableSkipsIfPluginAlreadyEnabled(): void
    {
        // Arrange
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('fileExists')->willReturnCallback(file_exists(...));
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir, 'test');

        // Act
        $subject->enable('plugin1');

        // Assert
        static::assertTrue(true);
    }

    public function testDisableSetsPluginToFalse(): void
    {
        // Arrange
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(file_exists(...));
        $fsStub
            ->method('putFileContents')
            ->willReturnCallback(static function ($path, $content) {
                file_put_contents($path, $content);
                return true;
            });

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->once())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir, 'test');

        // Act
        $subject->disable('plugin1');

        // Assert
        $config = include $this->configFile;
        static::assertFalse($config['plugin1']);
    }

    public function testDisableSkipsIfPluginNotInstalled(): void
    {
        // Arrange
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('fileExists')->willReturnCallback(file_exists(...));
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir, 'test');

        // Act
        $subject->disable('non-existent-plugin');

        // Assert
        static::assertTrue(true);
    }

    public function testDisableSkipsIfPluginAlreadyDisabled(): void
    {
        // Arrange
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('fileExists')->willReturnCallback(file_exists(...));
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir, 'test');

        // Act
        $subject->disable('plugin2');

        // Assert
        static::assertTrue(true);
    }

    public function testSetPluginConfigSkipsWhenConfigPathIsFalse(): void
    {
        // Arrange
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('putFileContents')->willReturn(false);
        $fsStub->method('fileExists')->willReturnCallback(file_exists(...));

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir, 'test');

        // Act
        $subject->setPluginConfig(['test-plugin' => true]);

        // Assert
        static::assertTrue(true);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }
        $files = array_diff(scandir($dir), ['.', '..']);
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        rmdir($dir);
    }
}
