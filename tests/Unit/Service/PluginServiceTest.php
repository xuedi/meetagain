<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\ExtendedFilesystem;
use App\Service\CommandService;
use App\Service\PluginService;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;

class PluginServiceTest extends TestCase
{
    private MockObject|CommandService $commandServiceMock;
    private MockObject|ExtendedFilesystem $filesystemMock;
    private PluginService $subject;
    private string $tempDir;
    private string $configFile;

    protected function setUp(): void
    {
        $this->commandServiceMock = $this->createMock(CommandService::class);
        $this->filesystemMock = $this->createMock(ExtendedFilesystem::class);

        // Create a temporary directory for testing
        $this->tempDir = sys_get_temp_dir() . '/plugin_service_test_' . uniqid();
        mkdir($this->tempDir, 0777, true);

        // Create a config directory
        $configDir = $this->tempDir . '/config';
        mkdir($configDir, 0777, true);

        // Create a plugins.php file with a valid return statement
        $this->configFile = $configDir . '/plugins.php';
        file_put_contents(
            $this->configFile,
            '<?php declare(strict_types=1); return ["plugin1" => true, "plugin2" => false];',
        );

        // Create a real instance of PluginService with mocked dependencies
        $this->subject = new PluginService($this->commandServiceMock, $this->filesystemMock);
    }

    /**
     * Test getAdminList method
     */
    public function testGetAdminList(): void
    {
        // Create plugin directories and manifest files
        $pluginDir = $this->tempDir . '/plugins';
        mkdir($pluginDir, 0777, true);
        mkdir($pluginDir . '/plugin1', 0777, true);
        mkdir($pluginDir . '/plugin2', 0777, true);

        // Create manifest files
        $manifest1 = [
            'name' => 'Plugin 1',
            'version' => '1.0.0',
            'description' => 'Test plugin 1',
        ];
        $manifest2 = [
            'name' => 'Plugin 2',
            'version' => '2.0.0',
            'description' => 'Test plugin 2',
        ];
        file_put_contents($pluginDir . '/plugin1/manifest.json', json_encode($manifest1));
        file_put_contents($pluginDir . '/plugin2/manifest.json', json_encode($manifest2));

        // Mock plugin directory parsing
        $pluginPaths = [$pluginDir . '/plugin1', $pluginDir . '/plugin2'];
        $this->filesystemMock
            ->method('getRealPath')
            ->willReturnCallback(function ($path) use ($pluginDir) {
                if ($path === PluginService::PLUGIN_DIR) {
                    return $pluginDir;
                }
                if ($path === PluginService::CONFIG_DIR) {
                    return $this->tempDir . '/config';
                }
                return false;
            });

        $this->filesystemMock
            ->expects($this->once())
            ->method('glob')
            ->with($pluginDir . '/*', GLOB_ONLYDIR)
            ->willReturn($pluginPaths);

        // Mock manifest file existence and content
        $this->filesystemMock
            ->method('fileExists')
            ->willReturnCallback(function ($path) {
                return file_exists($path);
            });

        $this->filesystemMock
            ->method('getFileContents')
            ->willReturnCallback(function ($path) {
                return file_get_contents($path);
            });

        // Call the method and verify the result
        $result = $this->subject->getAdminList();

        // Assert that we got the expected plugins
        $this->assertCount(2, $result);
        $this->assertEquals('Plugin 1', $result[0]['name']);
        $this->assertEquals('Plugin 2', $result[1]['name']);
        $this->assertEquals('1.0.0', $result[0]['version']);
        $this->assertEquals('2.0.0', $result[1]['version']);
        $this->assertEquals('Test plugin 1', $result[0]['description']);
        $this->assertEquals('Test plugin 2', $result[1]['description']);
    }

    /**
     * Test getActiveList method
     */
    public function testGetActiveList(): void
    {
        // Mock filesystem methods
        $this->filesystemMock
            ->method('getRealPath')
            ->willReturnCallback(function ($path) {
                if ($path === PluginService::CONFIG_DIR) {
                    return $this->tempDir . '/config';
                }
                return false;
            });

        // Call the method and verify the result
        $result = $this->subject->getActiveList();

        // Assert that we got the expected active plugins
        $this->assertIsArray($result);
        $this->assertContains('plugin1', $result);
        $this->assertNotContains('plugin2', $result);
    }

    /**
     * Test install method
     */
    public function testInstall(): void
    {
        // Mock filesystem methods
        $this->filesystemMock
            ->method('getRealPath')
            ->willReturnCallback(function ($path) {
                if ($path === PluginService::CONFIG_DIR) {
                    return $this->tempDir . '/config';
                }
                return false;
            });

        // Mock putFileContents to capture the config file content
        $capturedContent = null;
        $this->filesystemMock
            ->method('putFileContents')
            ->willReturnCallback(function ($path, $content) use (&$capturedContent) {
                $capturedContent = $content;
                file_put_contents($this->configFile, $content);
                return true;
            });

        // Verify that the commandService clearCache method will be called
        $this->commandServiceMock->expects($this->once())->method('clearCache');

        // Call the method
        $this->subject->install('test-plugin');

        // Verify that the plugin was installed (added to config file)
        $config = include $this->configFile;
        $this->assertIsArray($config);
        $this->assertArrayHasKey('test-plugin', $config);
        $this->assertFalse($config['test-plugin']);
    }

    /**
     * Test install method early return when plugin is already installed
     */
    public function testInstallEarlyReturnWhenAlreadyInstalled(): void
    {
        // Mock filesystem methods
        $this->filesystemMock
            ->method('getRealPath')
            ->willReturnCallback(function ($path) {
                if ($path === PluginService::CONFIG_DIR) {
                    return $this->tempDir . '/config';
                }
                return false;
            });

        // Mock putFileContents to capture the config file content
        $capturedContent = null;
        $this->filesystemMock
            ->method('putFileContents')
            ->willReturnCallback(function ($path, $content) use (&$capturedContent) {
                $capturedContent = $content;
                file_put_contents($this->configFile, $content);
                return true;
            });

        // First install the plugin
        $this->subject->install('test-plugin');

        // Reset the mock to verify it's not called again
        $this->commandServiceMock = $this->createMock(CommandService::class);
        $this->commandServiceMock->expects($this->never())->method('clearCache');

        // Create a new subject with the reset mock
        $this->subject = new PluginService($this->commandServiceMock, $this->filesystemMock);

        // Try to install the same plugin again
        $this->subject->install('test-plugin');

        // Verify that the plugin config wasn't changed (early return happened)
        $config = include $this->configFile;
        $this->assertIsArray($config);
        $this->assertArrayHasKey('test-plugin', $config);
        $this->assertFalse($config['test-plugin']);
    }

    /**
     * Test uninstall method
     */
    public function testUninstall(): void
    {
        // First install a plugin to uninstall
        $this->filesystemMock
            ->method('getRealPath')
            ->willReturnCallback(function ($path) {
                if ($path === PluginService::CONFIG_DIR) {
                    return $this->tempDir . '/config';
                }
                return false;
            });

        // Mock putFileContents to capture the config file content
        $capturedContent = null;
        $this->filesystemMock
            ->method('putFileContents')
            ->willReturnCallback(function ($path, $content) use (&$capturedContent) {
                $capturedContent = $content;
                file_put_contents($this->configFile, $content);
                return true;
            });

        // First install the plugin
        $this->subject->install('test-plugin');

        // Then uninstall it
        $this->subject->uninstall('test-plugin');

        // Verify that the plugin was uninstalled (removed from config file)
        $config = include $this->configFile;
        $this->assertIsArray($config);
        $this->assertArrayNotHasKey('test-plugin', $config);
    }

    /**
     * Test uninstall method early return when plugin is not installed
     */
    public function testUninstallEarlyReturnWhenNotInstalled(): void
    {
        // Mock filesystem methods
        $this->filesystemMock
            ->method('getRealPath')
            ->willReturnCallback(function ($path) {
                if ($path === PluginService::CONFIG_DIR) {
                    return $this->tempDir . '/config';
                }
                return false;
            });

        // Mock putFileContents to verify it's not called
        $this->filesystemMock->expects($this->never())->method('putFileContents');

        // Verify that the commandService clearCache method will not be called
        $this->commandServiceMock->expects($this->never())->method('clearCache');

        // Try to uninstall a plugin that doesn't exist
        $this->subject->uninstall('non-existent-plugin');

        // Verify that the config file wasn't changed
        $config = include $this->configFile;
        $this->assertIsArray($config);
        $this->assertArrayHasKey('plugin1', $config);
        $this->assertArrayHasKey('plugin2', $config);
        $this->assertArrayNotHasKey('non-existent-plugin', $config);
    }

    /**
     * Test enable method
     */
    public function testEnable(): void
    {
        // Mock filesystem methods
        $this->filesystemMock
            ->method('getRealPath')
            ->willReturnCallback(function ($path) {
                if ($path === PluginService::CONFIG_DIR) {
                    return $this->tempDir . '/config';
                }
                return false;
            });

        // Mock putFileContents to capture the config file content
        $capturedContent = null;
        $this->filesystemMock
            ->method('putFileContents')
            ->willReturnCallback(function ($path, $content) use (&$capturedContent) {
                $capturedContent = $content;
                file_put_contents($this->configFile, $content);
                return true;
            });

        // First install the plugin
        $this->subject->install('test-plugin');

        // Then enable it
        $this->subject->enable('test-plugin');

        // Verify that the plugin was enabled (set to true in config file)
        $config = include $this->configFile;
        $this->assertIsArray($config);
        $this->assertArrayHasKey('test-plugin', $config);
        $this->assertTrue($config['test-plugin']);
    }

    /**
     * Test enable method early return when plugin is not installed
     */
    public function testEnableEarlyReturnWhenNotInstalled(): void
    {
        // Mock filesystem methods
        $this->filesystemMock
            ->method('getRealPath')
            ->willReturnCallback(function ($path) {
                if ($path === PluginService::CONFIG_DIR) {
                    return $this->tempDir . '/config';
                }
                return false;
            });

        // Mock putFileContents to verify it's not called
        $this->filesystemMock->expects($this->never())->method('putFileContents');

        // Verify that the commandService clearCache method will not be called
        $this->commandServiceMock->expects($this->never())->method('clearCache');

        // Try to enable a plugin that isn't installed
        $this->subject->enable('non-existent-plugin');

        // Verify that the config file wasn't changed
        $config = include $this->configFile;
        $this->assertIsArray($config);
        $this->assertArrayNotHasKey('non-existent-plugin', $config);
    }

    /**
     * Test enable method early return when plugin is already enabled
     */
    public function testEnableEarlyReturnWhenAlreadyEnabled(): void
    {
        // Mock filesystem methods
        $this->filesystemMock
            ->method('getRealPath')
            ->willReturnCallback(function ($path) {
                if ($path === PluginService::CONFIG_DIR) {
                    return $this->tempDir . '/config';
                }
                return false;
            });

        // Mock putFileContents to capture the config file content
        $capturedContent = null;
        $this->filesystemMock
            ->method('putFileContents')
            ->willReturnCallback(function ($path, $content) use (&$capturedContent) {
                $capturedContent = $content;
                file_put_contents($this->configFile, $content);
                return true;
            });

        // First install and enable the plugin
        $this->subject->install('test-plugin');
        $this->subject->enable('test-plugin');

        // Reset the mock to verify it's not called again
        $this->commandServiceMock = $this->createMock(CommandService::class);
        $this->commandServiceMock->expects($this->never())->method('clearCache');

        // Create a new subject with the reset mock
        $this->subject = new PluginService($this->commandServiceMock, $this->filesystemMock);

        // Try to enable the plugin again
        $this->subject->enable('test-plugin');

        // Verify that the plugin config wasn't changed (early return happened)
        $config = include $this->configFile;
        $this->assertIsArray($config);
        $this->assertArrayHasKey('test-plugin', $config);
        $this->assertTrue($config['test-plugin']);
    }

    /**
     * Test disable method
     */
    public function testDisable(): void
    {
        // Mock filesystem methods
        $this->filesystemMock
            ->method('getRealPath')
            ->willReturnCallback(function ($path) {
                if ($path === PluginService::CONFIG_DIR) {
                    return $this->tempDir . '/config';
                }
                return false;
            });

        // Mock putFileContents to capture the config file content
        $capturedContent = null;
        $this->filesystemMock
            ->method('putFileContents')
            ->willReturnCallback(function ($path, $content) use (&$capturedContent) {
                $capturedContent = $content;
                file_put_contents($this->configFile, $content);
                return true;
            });

        // First install and enable the plugin
        $this->subject->install('test-plugin');
        $this->subject->enable('test-plugin');

        // Then disable it
        $this->subject->disable('test-plugin');

        // Verify that the plugin was disabled (set to false in config file)
        $config = include $this->configFile;
        $this->assertIsArray($config);
        $this->assertArrayHasKey('test-plugin', $config);
        $this->assertFalse($config['test-plugin']);
    }

    /**
     * Test disable method early return when plugin is not installed
     */
    public function testDisableEarlyReturnWhenNotInstalled(): void
    {
        // Mock filesystem methods
        $this->filesystemMock
            ->method('getRealPath')
            ->willReturnCallback(function ($path) {
                if ($path === PluginService::CONFIG_DIR) {
                    return $this->tempDir . '/config';
                }
                return false;
            });

        // Mock putFileContents to verify it's not called
        $this->filesystemMock->expects($this->never())->method('putFileContents');

        // Verify that the commandService clearCache method will not be called
        $this->commandServiceMock->expects($this->never())->method('clearCache');

        // Try to disable a plugin that isn't installed
        $this->subject->disable('non-existent-plugin');

        // Verify that the config file wasn't changed
        $config = include $this->configFile;
        $this->assertIsArray($config);
        $this->assertArrayNotHasKey('non-existent-plugin', $config);
    }

    /**
     * Test disable method early return when plugin is not enabled
     */
    public function testDisableEarlyReturnWhenNotEnabled(): void
    {
        // Mock filesystem methods
        $this->filesystemMock
            ->method('getRealPath')
            ->willReturnCallback(function ($path) {
                if ($path === PluginService::CONFIG_DIR) {
                    return $this->tempDir . '/config';
                }
                return false;
            });

        // Mock putFileContents to capture the config file content
        $capturedContent = null;
        $this->filesystemMock
            ->method('putFileContents')
            ->willReturnCallback(function ($path, $content) use (&$capturedContent) {
                $capturedContent = $content;
                file_put_contents($this->configFile, $content);
                return true;
            });

        // First install the plugin (but don't enable it)
        $this->subject->install('test-plugin');

        // Reset the mock to verify it's not called again
        $this->commandServiceMock = $this->createMock(CommandService::class);
        $this->commandServiceMock->expects($this->never())->method('clearCache');

        // Create a new subject with the reset mock
        $this->subject = new PluginService($this->commandServiceMock, $this->filesystemMock);

        // Try to disable the plugin that's not enabled
        $this->subject->disable('test-plugin');

        // Verify that the plugin config wasn't changed (early return happened)
        $config = include $this->configFile;
        $this->assertIsArray($config);
        $this->assertArrayHasKey('test-plugin', $config);
        $this->assertFalse($config['test-plugin']);
    }

    /**
     * Test setPluginConfig method early return when config path is false
     */
    public function testSetPluginConfigEarlyReturnWhenConfigPathIsFalse(): void
    {
        // Mock filesystem methods to return false for getRealPath
        $this->filesystemMock = $this->createMock(ExtendedFilesystem::class);
        $this->filesystemMock->method('getRealPath')->willReturn(false);

        // Mock putFileContents to verify it's not called
        $this->filesystemMock->expects($this->never())->method('putFileContents');

        // Verify that the commandService clearCache method will not be called
        $this->commandServiceMock->expects($this->never())->method('clearCache');

        // Create a new subject with the mocked filesystem
        $this->subject = new PluginService($this->commandServiceMock, $this->filesystemMock);

        // Try to set plugin config
        $this->subject->setPluginConfig(['test-plugin' => true]);

        // No assertion needed as we're verifying the methods are not called
    }

    /**
     * Test parsePluginDir method early return when plugin directory doesn't exist
     */
    public function testParsePluginDirEarlyReturnWhenPluginDirDoesntExist(): void
    {
        // Mock filesystem methods to return false for getRealPath
        $this->filesystemMock = $this->createMock(ExtendedFilesystem::class);
        $this->filesystemMock
            ->method('getRealPath')
            ->with(PluginService::PLUGIN_DIR)
            ->willReturn(false);

        // Mock glob to verify it's not called
        $this->filesystemMock->expects($this->never())->method('glob');

        // Create a new subject with the mocked filesystem
        $this->subject = new PluginService($this->commandServiceMock, $this->filesystemMock);

        // Call getAdminList which internally calls parsePluginDir
        $result = $this->subject->getAdminList();

        // Verify that an empty array is returned
        $this->assertIsArray($result);
        $this->assertEmpty($result);
    }
}
