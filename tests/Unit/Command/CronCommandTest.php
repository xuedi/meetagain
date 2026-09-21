<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\CronCommand;
use App\Metrics\Recorder;
use App\Service\Admin\CommandExecutionService;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class CronCommandTest extends TestCase
{
    public function testCommandHasCorrectName(): void
    {
        // Arrange
        $commandExecServiceStub = $this->createStub(CommandExecutionService::class);

        // Act
        $emStub = $this->createStub(EntityManagerInterface::class);
        $command = new CronCommand($emStub, $commandExecServiceStub, new Recorder(null, 'test'));

        // Assert
        static::assertSame('app:cron', $command->getName());
    }

    public function testCommandHasCorrectDescription(): void
    {
        // Arrange
        $commandExecServiceStub = $this->createStub(CommandExecutionService::class);

        // Act
        $emStub = $this->createStub(EntityManagerInterface::class);
        $command = new CronCommand($emStub, $commandExecServiceStub, new Recorder(null, 'test'));

        // Assert
        static::assertSame('cron manager to be called often, maybe every 5 min or so', $command->getDescription());
    }
}
