<?php declare(strict_types=1);

namespace Module\Task\Tests\Functional;

use Module\Task\Contract\Status;
use Module\Task\Internal\Repository\RunRepository;
use Module\Task\Internal\Runner;
use Module\Task\Internal\ScheduleProvider;
use Module\Task\Tests\Stub\Task;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

final class RunTest extends KernelTestCase
{
    public function testARunIsWrittenToTheRunLog(): void
    {
        // Arrange
        self::bootKernel();
        $container = self::getContainer();

        // Act
        $container->get(Runner::class)->run(Task::IDENTIFIER);

        // Assert
        $latest = $container->get(RunRepository::class)->findLatestPerIdentifier();
        self::assertArrayHasKey(Task::IDENTIFIER, $latest);
        self::assertSame(Status::ok, $latest[Task::IDENTIFIER]->getStatus());
        self::assertSame('run 1', $latest[Task::IDENTIFIER]->getMessage());
    }

    public function testTheLatestRunPerTaskWins(): void
    {
        // Arrange
        self::bootKernel();
        $container = self::getContainer();
        $runner = $container->get(Runner::class);
        $runner->run(Task::IDENTIFIER);
        $container->get(Task::class)->failWith = 'second run broke';

        // Act
        $runner->run(Task::IDENTIFIER);

        // Assert
        $latest = $container->get(RunRepository::class)->findLatestPerIdentifier()[Task::IDENTIFIER];
        self::assertSame(Status::exception, $latest->getStatus());
        self::assertSame('second run broke', $latest->getMessage());
    }

    public function testEveryRegisteredTaskIsOnTheSchedule(): void
    {
        // Arrange
        self::bootKernel();

        // Act
        $recurring = self::getContainer()->get(ScheduleProvider::class)->getSchedule()->getRecurringMessages();

        // Assert
        $triggers = array_map(static fn($message): string => (string) $message->getTrigger(), array_values($recurring));
        self::assertContains('*/5 * * * *', $triggers);
    }

    public function testTheRunCommandReportsTheResult(): void
    {
        // Arrange
        $kernel = self::bootKernel();
        $tester = new CommandTester(new Application($kernel)->find('app:task:run'));

        // Act
        $exitCode = $tester->execute(['identifier' => Task::IDENTIFIER]);

        // Assert
        self::assertSame(0, $exitCode);
        self::assertStringContainsString('ok: run 1', $tester->getDisplay());
    }
}
