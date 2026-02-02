<?php declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\FixturesLoadCommand;
use App\Service\FixturesLoaderInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class FixturesLoadCommandTest extends TestCase
{
    private CommandTester $commandTester;

    public function testReturnsSuccessWhenNoFixturesFound(): void
    {
        // Arrange: Create a command with a loader that returns no fixtures
        $command = new FixturesLoadCommand(new StubFixturesLoader([]));
        $this->setupCommandTester($command);

        // Act: Execute the command
        $exitCode = $this->commandTester->execute(['--group' => ['plugin']]);

        // Assert: Command succeeds
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testOutputsMessageWhenNoFixturesFound(): void
    {
        // Arrange: Create a command with a loader that returns no fixtures
        $command = new FixturesLoadCommand(new StubFixturesLoader([]));
        $this->setupCommandTester($command);

        // Act: Execute the command (not in quiet mode)
        $this->commandTester->execute(['--group' => ['plugin']]);

        // Assert: Message is displayed
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('No fixtures found for plugin', $output);
        $this->assertStringContainsString('Skipping', $output);
    }

    public function testNoOutputInQuietModeWhenNoFixturesFound(): void
    {
        // Arrange: Create a command with a loader that returns no fixtures
        $command = new FixturesLoadCommand(new StubFixturesLoader([]));
        $this->setupCommandTester($command);

        // Act: Execute the command in quiet mode (use verbosity flag)
        $this->commandTester->execute(['--group' => ['plugin']], ['verbosity' => OutputInterface::VERBOSITY_QUIET]);

        // Assert: No output (quiet mode suppresses messages)
        $output = $this->commandTester->getDisplay();
        $this->assertEmpty($output);
    }

    public function testDelegatesToDoctrineCommandWhenFixturesExist(): void
    {
        // Arrange: Fixtures exist for the specified group
        $mockFixture = $this->createStub(FixtureInterface::class);
        $command = new FixturesLoadCommand(new StubFixturesLoader([$mockFixture]));

        // Mock the doctrine:fixtures:load command
        $doctrineCommand = $this->createMock(Command::class);
        $doctrineCommand->expects($this->once())->method('run')->willReturn(Command::SUCCESS);

        // Mock the application to return our mocked doctrine command
        $application = $this->createMock(Application::class);
        $application
            ->expects($this->once())
            ->method('find')
            ->with('doctrine:fixtures:load')
            ->willReturn($doctrineCommand);

        $command->setApplication($application);
        $this->commandTester = new CommandTester($command);

        // Act: Execute the command
        $exitCode = $this->commandTester->execute(['--group' => ['plugin']]);

        // Assert: Command delegates to doctrine command
        $this->assertSame(Command::SUCCESS, $exitCode);
    }

    public function testHandlesMultipleGroups(): void
    {
        // Arrange: No fixtures for multiple groups
        $command = new FixturesLoadCommand(new StubFixturesLoader([]));
        $this->setupCommandTester($command);

        // Act: Execute the command with multiple groups
        $this->commandTester->execute(['--group' => ['plugin', 'test']]);

        // Assert: Message includes all groups
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('plugin, test', $output);
    }

    public function testHandlesNoGroupOption(): void
    {
        // Arrange: No fixtures exist at all
        $command = new FixturesLoadCommand(new StubFixturesLoader([]));
        $this->setupCommandTester($command);

        // Act: Execute without --group option
        $this->commandTester->execute([]);

        // Assert: Message mentions "all groups"
        $output = $this->commandTester->getDisplay();
        $this->assertStringContainsString('all groups', $output);
    }

    private function setupCommandTester(Command $command): void
    {
        $application = new Application();
        $command->setApplication($application);

        $this->commandTester = new CommandTester($command);
    }
}

/**
 * Stub fixtures loader for testing purposes.
 * Implements FixturesLoaderInterface to provide testable behavior.
 */
class StubFixturesLoader implements FixturesLoaderInterface
{
    private array $fixtures = [];

    public function __construct(array $fixtures = [])
    {
        $this->fixtures = $fixtures;
    }

    public function getFixtures(array $groups = []): array
    {
        return $this->fixtures;
    }
}
