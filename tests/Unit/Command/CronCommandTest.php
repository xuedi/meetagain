<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\CronCommand;
use App\Service\Admin\CommandExecutionService;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class CronCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        // Arrange
        $loggerStub = $this->createStub(LoggerInterface::class);
        $commandExecServiceStub = $this->createStub(CommandExecutionService::class);

        // Act
        $command = new CronCommand($loggerStub, $commandExecServiceStub);

        // Assert
        static::assertSame('app:cron', $command->getName());
    }

    public function testCommandHasCorrectDescription(): void
    {
        // Arrange
        $loggerStub = $this->createStub(LoggerInterface::class);
        $commandExecServiceStub = $this->createStub(CommandExecutionService::class);

        // Act
        $command = new CronCommand($loggerStub, $commandExecServiceStub);

        // Assert
        static::assertSame('cron manager to be called often, maybe every 5 min or so', $command->getDescription());
    }
}
