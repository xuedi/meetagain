<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\PluginToggleCommand;
use App\Repository\PluginActivationRepository;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class PluginToggleCommandTest extends TestCase
{
    /** @var array<string, bool> */
    private array $state = [];

    /** @var list<string> */
    private array $saved = [];

    public function testAllSwitchesARequirementOnBeforeItsDependent(): void
    {
        // Arrange
        $tester = $this->tester(['books' => [], 'films' => ['books'], 'pinned' => []]);

        // Act
        $exitCode = $tester->execute(['state' => 'on', 'plugins' => ['all']]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertSame(['books', 'films'], $this->saved);
    }

    public function testAllSwitchesADependentOffBeforeItsRequirement(): void
    {
        // Arrange
        $this->state = ['books' => true, 'films' => true];
        $tester = $this->tester(['books' => [], 'films' => ['books']]);

        // Act
        $exitCode = $tester->execute(['state' => 'off', 'plugins' => ['all']]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertSame(['films', 'books'], $this->saved);
    }

    public function testAPluginWhoseRequirementStaysOffIsReported(): void
    {
        // Arrange
        $tester = $this->tester(['books' => [], 'films' => ['books']]);

        // Act
        $exitCode = $tester->execute(['state' => 'on', 'plugins' => ['films']]);

        // Assert
        static::assertSame(Command::FAILURE, $exitCode);
        static::assertSame([], $this->saved);
        static::assertStringContainsString('films stays off', $tester->getDisplay());
    }

    public function testASystemPluginIsRefused(): void
    {
        // Arrange
        $tester = $this->tester(['books' => [], 'pinned' => []]);

        // Act
        $exitCode = $tester->execute(['state' => 'off', 'plugins' => ['pinned']]);

        // Assert
        static::assertSame(Command::FAILURE, $exitCode);
        static::assertSame([], $this->saved);
    }

    public function testAStateOtherThanOnOrOffIsRefused(): void
    {
        // Arrange
        $tester = $this->tester(['books' => []]);

        // Act
        $exitCode = $tester->execute(['state' => 'enable', 'plugins' => ['books']]);

        // Assert
        static::assertSame(Command::FAILURE, $exitCode);
    }

    /**
     * @param array<string, list<string>> $requires
     */
    private function tester(array $requires): CommandTester
    {
        $plugins = [];
        foreach ($requires as $key => $required) {
            $plugins[$key] = [
                'name' => $key,
                'version' => '1.0',
                'description' => '',
                'icon' => 'plug',
                'group_activatable' => true,
                'system' => $key === 'pinned',
                'requires' => $required,
            ];
        }

        $repository = $this->createStub(PluginActivationRepository::class);
        $repository->method('stateByKey')->willReturnCallback(fn(): array => $this->state);
        $repository
            ->method('save')
            ->willReturnCallback(function (string $key, bool $enabled): void {
                $this->state[$key] = $enabled;
                $this->saved[] = $key;
            });

        return new CommandTester(new PluginToggleCommand(
            new PluginService($plugins, $repository, new ArrayAdapter(), new ArrayAdapter(), new ArrayAdapter(), new MockClock()),
        ));
    }
}
