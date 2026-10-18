<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\CronCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CronCommandTest extends TestCase
{
    public function testTheCommandSucceedsWithoutRunningAnything(): void
    {
        // Arrange
        $tester = new CommandTester(new CronCommand());

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        static::assertSame(0, $exitCode);
        static::assertStringContainsString('replaced by the task workers', $tester->getDisplay());
    }
}
