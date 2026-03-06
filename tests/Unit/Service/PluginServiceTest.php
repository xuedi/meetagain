<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\ExtendedFilesystem;
use App\Filter\Plugin\PluginListFilterInterface;
use App\Service\CommandService;
use App\Service\PluginService;
use PHPUnit\Framework\TestCase;

class PluginServiceTest extends TestCase
{
    private string $tempDir;
    private string $configFile;
    private string $envConfigFile;

    protected function setUp(): void
    {
        // Arrange: create temporary directory structure for testing
        $this->tempDir = sys_get_temp_dir() . '/plugin_service_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0777, true);

        $this->configFile = $configDir . '/plugins.php';
        $this->envConfigFile = $configDir . '/plugins_test.php';
        file_put_contents(
            $this->envConfigFile,
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
        $fsMock->method('exists')->willReturnCallback(fn($path) => is_dir($path) || file_exists($path));
        $fsMock->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));
        $fsMock->method('getFileContents')->willReturnCallback(fn($path) => file_get_contents($path));

        $subject = new PluginService($this->createStub(CommandService::class), $fsMock, $this->tempDir, 'test');

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

        $subject = new PluginService($this->createStub(CommandService::class), $fsMock, $this->tempDir, 'test');

        // Act: get admin list
        $result = $subject->getAdminList();

        // Assert: returns empty array
        $this->assertSame([], $result);
    }

    public function testGetActiveListReturnsOnlyEnabledPlugins(): void
    {
        // Arrange: mock filesystem to return config directory
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));

        $subject = new PluginService($this->createStub(CommandService::class), $fsStub, $this->tempDir, 'test');

        // Act: get active list
        $result = $subject->getActiveList();

        // Assert: returns only enabled plugins (plugin1 is true, plugin2 is false)
        $this->assertContains('plugin1', $result);
        $this->assertNotContains('plugin2', $result);
    }

    public function testGetGloballyActiveListIgnoresFilters(): void
    {
        // Arrange: service with a filter that would restrict the list
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));

        $filterStub = $this->createStub(PluginListFilterInterface::class);
        $filterStub->method('filterActivePlugins')->willReturn([]);

        $subject = new PluginService(
            $this->createStub(CommandService::class),
            $fsStub,
            $this->tempDir,
            'test',
            [$filterStub],
        );

        // Act: get globally active list — filters must NOT be applied
        $result = $subject->getGloballyActiveList();

        // Assert: plugin1 still visible regardless of filter
        $this->assertContains('plugin1', $result);
    }

    public function testGetActiveListAppliesFilterAndIntersects(): void
    {
        // Arrange: plugin1 must have a manifest.json to be group-activatable and thus filterable
        $pluginDir = $this->tempDir . '/plugins';
        mkdir($pluginDir . '/plugin1', 0777, true);
        file_put_contents($pluginDir . '/plugin1/manifest.json', json_encode([
            'name' => 'Plugin 1',
            'description' => 'Group-activatable plugin',
        ]));

        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));
        $fsStub->method('exists')->willReturnCallback(fn($path) => is_dir($path));
        $fsStub->method('glob')->willReturn([$pluginDir . '/plugin1']);
        $fsStub->method('getFileContents')->willReturnCallback(fn($path) => file_get_contents($path));

        $filter = $this->createMock(PluginListFilterInterface::class);
        $filter->expects($this->once())->method('filterActivePlugins')->with(['plugin1'])->willReturn([]);

        $subject = new PluginService(
            $this->createStub(CommandService::class),
            $fsStub,
            $this->tempDir,
            'test',
            [$filter],
        );

        // Act
        $result = $subject->getActiveList();

        // Assert: filter restricts group-activatable plugin1 out; core_navigation always remains
        $this->assertNotContains('plugin1', $result);
        $this->assertContains('core_navigation', $result);
    }

    public function testGetActiveListReturnsGlobalListWhenFilterReturnsNull(): void
    {
        // Arrange: filter returns null (no opinion)
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));

        $filterStub = $this->createStub(PluginListFilterInterface::class);
        $filterStub->method('filterActivePlugins')->willReturn(null);

        $subject = new PluginService(
            $this->createStub(CommandService::class),
            $fsStub,
            $this->tempDir,
            'test',
            [$filterStub],
        );

        // Act
        $result = $subject->getActiveList();

        // Assert: null filter means no restriction — globally active plugins returned
        $this->assertContains('plugin1', $result);
        $this->assertNotContains('plugin2', $result);
    }

    public function testGetActivatableByGroupListExcludesGroupActivatableFalsePlugins(): void
    {
        // Arrange: two plugins, one with group_activatable: false
        $pluginDir = $this->tempDir . '/plugins';
        mkdir($pluginDir, 0777, true);
        mkdir($pluginDir . '/plugin1', 0777, true);
        mkdir($pluginDir . '/hidden_plugin', 0777, true);

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
        $fsStub->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));
        $fsStub->method('getFileContents')->willReturnCallback(fn($path) => file_get_contents($path));

        // plugin1 is globally active, hidden_plugin is not in test config
        $subject = new PluginService($this->createStub(CommandService::class), $fsStub, $this->tempDir, 'test');

        // Act
        $result = $subject->getActivatableByGroupList();

        // Assert: only plugin1 appears (plugin1 active + no group_activatable restriction)
        $keys = array_column($result, 'key');
        $this->assertContains('plugin1', $keys);
        $this->assertNotContains('hidden_plugin', $keys);
    }

    public function testInstallAddsPluginToConfigAsDisabled(): void
    {
        // Arrange: mock filesystem for config operations
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));
        $fsStub
            ->method('putFileContents')
            ->willReturnCallback(function ($path, $content) {
                // Write to the standard plugins.php file
                file_put_contents($path, $content);
                return true;
            });

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->once())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir, 'test');

        // Act: install new plugin
        $subject->install('test-plugin');

        // Assert: plugin is added as disabled to the main plugins.php file
        $config = include $this->configFile;
        $this->assertArrayHasKey('test-plugin', $config);
        $this->assertFalse($config['test-plugin']);
    }

    public function testInstallSkipsIfPluginAlreadyInstalled(): void
    {
        // Arrange: mock filesystem for config operations
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir, 'test');

        // Act: try to install already installed plugin
        $subject->install('plugin1');

        // Assert: config unchanged (plugin1 was already in config)
        $config = include $this->envConfigFile;
        $this->assertTrue($config['plugin1']);
    }

    public function testUninstallRemovesPluginFromConfig(): void
    {
        // Arrange: mock filesystem for config operations
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));
        $fsStub
            ->method('putFileContents')
            ->willReturnCallback(function ($path, $content) {
                file_put_contents($path, $content);
                return true;
            });

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->once())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir, 'test');

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
        $fsMock->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir, 'test');

        // Act: try to uninstall non-existent plugin
        $subject->uninstall('non-existent-plugin');

        // Assert: config unchanged
        $config = include $this->envConfigFile;
        $this->assertArrayNotHasKey('non-existent-plugin', $config);
    }

    public function testEnableSetsPluginToTrue(): void
    {
        // Arrange: mock filesystem for config operations
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));
        $fsStub
            ->method('putFileContents')
            ->willReturnCallback(function ($path, $content) {
                file_put_contents($path, $content);
                return true;
            });

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->once())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir, 'test');

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
        $fsMock->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir, 'test');

        // Act: try to enable non-existent plugin
        $subject->enable('non-existent-plugin');

        // Assert: nothing happens
        $this->assertTrue(true);
    }

    public function testEnableSkipsIfPluginAlreadyEnabled(): void
    {
        // Arrange: mock filesystem for config operations
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir, 'test');

        // Act: try to enable already enabled plugin (plugin1 is true)
        $subject->enable('plugin1');

        // Assert: nothing happens
        $this->assertTrue(true);
    }

    public function testDisableSetsPluginToFalse(): void
    {
        // Arrange: mock filesystem for config operations
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));
        $fsStub
            ->method('putFileContents')
            ->willReturnCallback(function ($path, $content) {
                file_put_contents($path, $content);
                return true;
            });

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->once())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir, 'test');

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
        $fsMock->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir, 'test');

        // Act: try to disable non-existent plugin
        $subject->disable('non-existent-plugin');

        // Assert: nothing happens
        $this->assertTrue(true);
    }

    public function testDisableSkipsIfPluginAlreadyDisabled(): void
    {
        // Arrange: mock filesystem for config operations
        $fsMock = $this->createMock(ExtendedFilesystem::class);
        $fsMock->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));
        $fsMock->expects($this->never())->method('putFileContents');

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsMock, $this->tempDir, 'test');

        // Act: try to disable already disabled plugin (plugin2 is false)
        $subject->disable('plugin2');

        // Assert: nothing happens
        $this->assertTrue(true);
    }

    public function testSetPluginConfigSkipsWhenConfigPathIsFalse(): void
    {
        // Arrange: mock filesystem to return false for config path
        $fsStub = $this->createStub(ExtendedFilesystem::class);
        $fsStub->method('putFileContents')->willReturn(false);
        $fsStub->method('fileExists')->willReturnCallback(fn($path) => file_exists($path));

        $cmdMock = $this->createMock(CommandService::class);
        $cmdMock->expects($this->never())->method('clearCache');

        $subject = new PluginService($cmdMock, $fsStub, $this->tempDir, 'test');

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
