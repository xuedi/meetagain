<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\CleanupCommand;
use App\Service\CleanupService;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class CleanupCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        $cleanupServiceStub = $this->createStub(CleanupService::class);
        $command = new CleanupCommand($cleanupServiceStub);

        $this->assertSame('app:cleanup', $command->getName());
    }

    public function testCommandHasCorrectDescription(): void
    {
        $cleanupServiceStub = $this->createStub(CleanupService::class);
        $command = new CleanupCommand($cleanupServiceStub);

        $this->assertSame('does certain cleanup tasks', $command->getDescription());
    }

    public function testExecuteCallsCleanupMethodsAndReturnsSuccess(): void
    {
        $cleanupServiceMock = $this->createMock(CleanupService::class);
        $cleanupServiceMock
            ->expects($this->once())
            ->method('removeImageCache');
        $cleanupServiceMock
            ->expects($this->once())
            ->method('removeGhostedRegistrations');

        $command = new CleanupCommand($cleanupServiceMock);
        $commandTester = new CommandTester($command);

        $exitCode = $commandTester->execute([]);

        $this->assertSame(Command::SUCCESS, $exitCode);
        $this->assertStringContainsString('Clean image cache', $commandTester->getDisplay());
        $this->assertStringContainsString('Clean registrations', $commandTester->getDisplay());
    }
}
