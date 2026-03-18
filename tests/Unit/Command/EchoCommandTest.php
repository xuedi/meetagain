<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\EchoCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class EchoCommandTest extends TestCase
{
    private EchoCommand $command;
    private CommandTester $commandTester;

    protected function setUp(): void
    {
        $this->command = new EchoCommand();
        $this->commandTester = new CommandTester($this->command);
    }

    public function testCommandHasCorrectName(): void
    {
        static::assertSame('app:echo', $this->command->getName());
    }

    public function testCommandHasCorrectDescription(): void
    {
        static::assertSame('simple command for testing, echos parameter', $this->command->getDescription());
    }

    public function testExecuteReturnsSuccessAndEchosMessage(): void
    {
        $exitCode = $this->commandTester->execute(['message' => 'Hello World']);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString('Echo command: Hello World', $this->commandTester->getDisplay());
    }

    public function testExecuteWithSpecialCharacters(): void
    {
        $exitCode = $this->commandTester->execute(['message' => 'Test with "quotes" and \'apostrophes\'']);

        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringContainsString(
            'Test with "quotes" and \'apostrophes\'',
            $this->commandTester->getDisplay(),
        );
    }
}
