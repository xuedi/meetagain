<?php declare(strict_types=1);

namespace Tests\Unit\DataHotfix\Hotfixes;

use App\DataHotfix\Hotfixes\PluginActivationInitialState;
use App\Enum\UserRole;
use App\Repository\UserRepository;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;

class PluginActivationInitialStateTest extends TestCase
{
    private string $legacyFile = '';

    protected function setUp(): void
    {
        $this->legacyFile = sys_get_temp_dir() . '/plugins-legacy-' . uniqid('', true) . '.php';
    }

    protected function tearDown(): void
    {
        if (is_file($this->legacyFile)) {
            unlink($this->legacyFile);
        }
    }

    public function testALegacyPluginsFileIsCopied(): void
    {
        // Arrange
        file_put_contents($this->legacyFile, "<?php return ['books' => true, 'films' => false];");
        $plugins = $this->createMock(PluginService::class);
        $plugins->method('hasState')->willReturn(false);
        $plugins->expects($this->once())->method('adopt')->with(['books' => true, 'films' => false]);

        // Act
        $this->hotfix($plugins, admins: 1)->execute();
    }

    public function testAnExistingInstallWithoutALegacyFileSwitchesEveryPluginOn(): void
    {
        // Arrange
        $plugins = $this->createMock(PluginService::class);
        $plugins->method('hasState')->willReturn(false);
        $plugins->method('getInstalledKeys')->willReturn(['books', 'films']);
        $plugins->expects($this->once())->method('adopt')->with(['books' => true, 'films' => true]);

        // Act
        $this->hotfix($plugins, admins: 1)->execute();
    }

    public function testAFreshInstallStartsWithEveryPluginOff(): void
    {
        // Arrange
        $plugins = $this->createMock(PluginService::class);
        $plugins->method('hasState')->willReturn(false);
        $plugins->expects($this->never())->method('adopt');

        // Act
        $this->hotfix($plugins, admins: 0)->execute();
    }

    public function testAnInstallThatAlreadyHasAStateKeepsIt(): void
    {
        // Arrange
        file_put_contents($this->legacyFile, "<?php return ['books' => true];");
        $plugins = $this->createMock(PluginService::class);
        $plugins->method('hasState')->willReturn(true);
        $plugins->expects($this->never())->method('adopt');

        // Act
        $this->hotfix($plugins, admins: 1)->execute();
    }

    private function hotfix(PluginService $plugins, int $admins): PluginActivationInitialState
    {
        $userRepository = $this->createStub(UserRepository::class);
        $userRepository->method('count')->willReturnCallback(static fn(array $criteria): int => $criteria === ['role' => UserRole::Admin] ? $admins : 99);

        return new PluginActivationInitialState($plugins, $userRepository, $this->legacyFile);
    }
}
