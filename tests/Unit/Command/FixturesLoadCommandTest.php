<?php declare(strict_types=1);

namespace App\Tests\Unit\Command;

use App\Command\FixturesLoadCommand;
use App\Command\FixturesLoaderInterface;
use Doctrine\Common\DataFixtures\FixtureInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class FixturesLoadCommandTest extends TestCase
{
    private CommandTester $commandTester;

    public function testReturnsSuccessWhenNoFixturesFound(): void
    {
        // Arrange
        $command = new FixturesLoadCommand(new StubFixturesLoader([]));
        $this->setupCommandTester($command);

        // Act
        $exitCode = $this->commandTester->execute(['--group' => ['plugin']]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testOutputsMessageWhenNoFixturesFound(): void
    {
        // Arrange
        $command = new FixturesLoadCommand(new StubFixturesLoader([]));
        $this->setupCommandTester($command);

        // Act
        $this->commandTester->execute(['--group' => ['plugin']]);

        // Assert
        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('No fixtures found for plugin', $output);
        static::assertStringContainsString('Skipping', $output);
    }

    public function testNoOutputInQuietModeWhenNoFixturesFound(): void
    {
        // Arrange
        $command = new FixturesLoadCommand(new StubFixturesLoader([]));
        $this->setupCommandTester($command);

        // Act
        $this->commandTester->execute(['--group' => ['plugin']], ['verbosity' => OutputInterface::VERBOSITY_QUIET]);

        // Assert
        $output = $this->commandTester->getDisplay();
        static::assertEmpty($output);
    }

    public function testDelegatesToDoctrineCommandWhenFixturesExist(): void
    {
        // Arrange
        $mockFixture = $this->createStub(FixtureInterface::class);
        $command = new FixturesLoadCommand(new StubFixturesLoader([$mockFixture]));

        $doctrineCommand = $this->createMock(Command::class);
        $doctrineCommand->expects($this->once())->method('run')->willReturn(Command::SUCCESS);

        $application = $this->createMock(Application::class);
        $application->expects($this->once())->method('find')->with('doctrine:fixtures:load')->willReturn($doctrineCommand);

        $command->setApplication($application);
        $this->commandTester = new CommandTester($command);

        // Act
        $exitCode = $this->commandTester->execute(['--group' => ['plugin']]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testHandlesMultipleGroups(): void
    {
        // Arrange
        $command = new FixturesLoadCommand(new StubFixturesLoader([]));
        $this->setupCommandTester($command);

        // Act
        $this->commandTester->execute(['--group' => ['plugin', 'test']]);

        // Assert
        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('plugin, test', $output);
    }

    public function testHandlesNoGroupOption(): void
    {
        // Arrange
        $command = new FixturesLoadCommand(new StubFixturesLoader([]));
        $this->setupCommandTester($command);

        // Act
        $this->commandTester->execute([]);

        // Assert
        $output = $this->commandTester->getDisplay();
        static::assertStringContainsString('all groups', $output);
    }

    private function setupCommandTester(Command $command): void
    {
        $application = new Application();
        $command->setApplication($application);

        $this->commandTester = new CommandTester($command);
    }
}

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
