<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\CronCommand;
use App\CronTaskInterface;
use App\Enum\CronTaskStatus;
use App\Metrics\Recorder;
use App\Service\Admin\CommandExecutionService;
use App\ValueObject\CronTaskResult;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

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

    public function testTheTaskOptionRunsOnlyTheNamedTasks(): void
    {
        // Arrange
        $ran = [];
        $tasks = [$this->task('alpha', $ran), $this->task('beta', $ran), $this->task('gamma', $ran)];
        $command = new CronCommand(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(CommandExecutionService::class),
            new Recorder(null, 'test'),
            $tasks,
        );

        // Act
        new CommandTester($command)->execute(['--task' => ['beta', 'gamma']]);

        // Assert
        static::assertSame(['beta', 'gamma'], $ran);
    }

    /**
     * @param list<string> $ran
     */
    private function task(string $identifier, array &$ran): CronTaskInterface
    {
        return new class($identifier, $ran) implements CronTaskInterface {
            /**
             * @param list<string> $ran
             */
            public function __construct(
                private readonly string $identifier,
                private array &$ran,
            ) {}

            public function getIdentifier(): string
            {
                return $this->identifier;
            }

            public function runCronTask(OutputInterface $output): CronTaskResult
            {
                $this->ran[] = $this->identifier;

                return new CronTaskResult($this->identifier, CronTaskStatus::ok, '');
            }
        };
    }
}
