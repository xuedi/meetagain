<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\ExtendedFilesystem;
use App\Service\CommandService;
use App\Service\PluginService;
use PHPUnit\Framework\TestCase;

class PluginServiceTest extends TestCase
{
    private string $tempDir;
    private string $configFile;

    protected function setUp(): void
    {
        // Arrange: create temporary directory structure for testing
        $this->tempDir = sys_get_temp_dir() . '/plugin_service_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0777, true);

        $this->configFile = $configDir . '/plugins.php';
        file_put_contents(
            $this->configFile,
            '<?php declare(strict_types=1); return ["plugin1" => true, "plugin2" => false];',
        );
    }

    protected function tearDown(): void
    {
        // Cleanup: remove temporary directory
        $this->removeDirectory($this->tempDir);
    }

    public function testGetAdminListReturnsPluginManifestData(): void
    {
        // Arrange: create plugin directories with manifest files
        $pluginDir = $this->tempDir . '/plugins';
        mkdir($pluginDir, 0777, true);
        mkdir($pluginDir . '/plugin1', 0777, true);
        mkdir($pluginDir . '/plugin2', 0777, true);

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
        $fsMock->method('getRealPath')->willReturnCallback(fn($path) => match ($path) {
            $this->tempDir . '/plugins' => $pluginDir,
            $this->tempDir . '/config' => $this->tempDir . '/config',
            default => false,
        });
        $fsMock
            ->expects($this->once())
            ->method('glob')
            ->with($pluginDir . '/*', GLOB_ONLYDIR)
            ->willReturn([$pluginDir . '/plugin1', $pluginDir . '/plugin2']);
        $fsMock->method('exists')->willReturnCallback(fn($path) => is_dir($path) || file_exists($path));
        $fsMock->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));
        $fsMock->method('getFileContents')->willReturnCallback(fn($path) => file_get_contents($path));

        $subject = new PluginService($this->createStub(CommandService::class), $fsMock, $this->tempDir);

        // Act: get admin list
        $result = $subject->getAdminList();

        // Assert: returns plugin manifest data
        $this->assertCount(2, $result);
        $this->assertSame('Plugin 1', $result[0]['name']);
        $this->assertSame('1.0.0', $result[0]['version']);
        $this->assertSame('Test plugin 1', $result[0]['description']);
        $this->assertSame('Plugin 2', $result[1]['name']);
        $this->assertSame('2.0.0', $result[1]['version']);
    }

    public function testGetAdminListReturnsEmptyArrayWhenPluginDirNotFound(): void
    {
        // Arrange: mock filesystem to return false for plugin directory
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('exists')->willReturn(false);
        $fsMock->expects($this->never())->method('glob');

        $subject = new PluginService($this->createStub(CommandService::class), $fsMock, $this->tempDir);

        // Act: get admin list
        $result = $subject->getAdminList();

        // Assert: returns empty array
        $this->assertSame([], $result);
    }

    public function testGetActiveListReturnsOnlyEnabledPlugins(): void
    {
        // Arrange: mock filesystem to return config directory
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturn(true);

        $subject = new PluginService($this->createStub(CommandService::class), $fsStub, $this->tempDir);

        // Act: get active list
        $result = $subject->getActiveList();

        // Assert: returns only enabled plugins (plugin1 is true, plugin2 is false)
        $this->assertContains('plugin1', $result);
        $this->assertNotContains('plugin2', $result);
    }

    public function testInstallAddsPluginToConfigAsDisabled(): void
    {
        // Arrange: mock filesystem for config operations
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturn(true);
        $fsStub->method('putFileContents')->willReturnCallback(function ($path, $content) {
            file_put_contents($this->configFile, $content);
            return true;
        });

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->once())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir);

        // Act: install new plugin
        $subject->install('test-plugin');

        // Assert: plugin is added as disabled
        $config = include $this->configFile;
        $this->assertArrayHasKey('test-plugin', $config);
        $this->assertFalse($config['test-plugin']);
    }

    public function testInstallSkipsIfPluginAlreadyInstalled(): void
    {
        // Arrange: mock filesystem for config operations
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturn(true);

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir);

        // Act: try to install already installed plugin
        $subject->install('plugin1');

        // Assert: config unchanged (plugin1 was already in config)
        $config = include $this->configFile;
        $this->assertTrue($config['plugin1']);
    }

    public function testUninstallRemovesPluginFromConfig(): void
    {
        // Arrange: mock filesystem for config operations
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturn(true);
        $fsStub->method('putFileContents')->willReturnCallback(function ($path, $content) {
            file_put_contents($this->configFile, $content);
            return true;
        });

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->once())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir);

        // Act: uninstall plugin
        $subject->uninstall('plugin1');

        // Assert: plugin is removed from config
        $config = include $this->configFile;
        $this->assertArrayNotHasKey('plugin1', $config);
    }

    public function testUninstallSkipsIfPluginNotInstalled(): void
    {
        // Arrange: mock filesystem for config operations
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('fileExists')->willReturn(true);
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir);

        // Act: try to uninstall non-existent plugin
        $subject->uninstall('non-existent-plugin');

        // Assert: config unchanged
        $config = include $this->configFile;
        $this->assertArrayNotHasKey('non-existent-plugin', $config);
    }

    public function testEnableSetsPluginToTrue(): void
    {
        // Arrange: mock filesystem for config operations
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturn(true);
        $fsStub->method('putFileContents')->willReturnCallback(function ($path, $content) {
            file_put_contents($this->configFile, $content);
            return true;
        });

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->once())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir);

        // Act: enable disabled plugin (plugin2 is false)
        $subject->enable('plugin2');

        // Assert: plugin is enabled
        $config = include $this->configFile;
        $this->assertTrue($config['plugin2']);
    }

    public function testEnableSkipsIfPluginNotInstalled(): void
    {
        // Arrange: mock filesystem for config operations
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('fileExists')->willReturn(true);
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir);

        // Act: try to enable non-existent plugin
        $subject->enable('non-existent-plugin');

        // Assert: nothing happens
        $this->assertTrue(true);
    }

    public function testEnableSkipsIfPluginAlreadyEnabled(): void
    {
        // Arrange: mock filesystem for config operations
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('fileExists')->willReturn(true);
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir);

        // Act: try to enable already enabled plugin (plugin1 is true)
        $subject->enable('plugin1');

        // Assert: nothing happens
        $this->assertTrue(true);
    }

    public function testDisableSetsPluginToFalse(): void
    {
        // Arrange: mock filesystem for config operations
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturn(true);
        $fsStub->method('putFileContents')->willReturnCallback(function ($path, $content) {
            file_put_contents($this->configFile, $content);
            return true;
        });

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->once())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir);

        // Act: disable enabled plugin (plugin1 is true)
        $subject->disable('plugin1');

        // Assert: plugin is disabled
        $config = include $this->configFile;
        $this->assertFalse($config['plugin1']);
    }

    public function testDisableSkipsIfPluginNotInstalled(): void
    {
        // Arrange: mock filesystem for config operations
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('fileExists')->willReturn(true);
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir);

        // Act: try to disable non-existent plugin
        $subject->disable('non-existent-plugin');

        // Assert: nothing happens
        $this->assertTrue(true);
    }

    public function testDisableSkipsIfPluginAlreadyDisabled(): void
    {
        // Arrange: mock filesystem for config operations
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('fileExists')->willReturn(true);
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir);

        // Act: try to disable already disabled plugin (plugin2 is false)
        $subject->disable('plugin2');

        // Assert: nothing happens
        $this->assertTrue(true);
    }

    public function testSetPluginConfigSkipsWhenConfigPathIsFalse(): void
    {
        // Arrange: mock filesystem to return false for config path
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('putFileContents')->willReturn(false);

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir);

        // Act: try to set plugin config
        $subject->setPluginConfig(['test-plugin' => true]);

        // Assert: nothing happens (verified by mock expectations)
        $this->assertTrue(true);
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
