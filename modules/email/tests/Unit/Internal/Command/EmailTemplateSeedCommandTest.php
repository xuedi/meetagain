<?php declare(strict_types=1);

namespace Module\Email\Tests\Unit\Internal\Command;

use Module\Email\Internal\Command\EmailTemplateSeedCommand;
use Module\Email\Internal\TemplateSeedAction;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class EmailTemplateSeedCommandTest extends TestCase
{
    private const array SEEDED = ['templates' => ['welcome'], 'translations' => ['welcome (en)'], 'overwritten' => ['digest (de)']];

    public function testWithoutTheFlagNothingIsOverwritten(): void
    {
        // Arrange
        $action = $this->createMock(TemplateSeedAction::class);
        $action->expects($this->once())->method('seed')->with(false)->willReturn(self::SEEDED);
        $tester = new CommandTester(new EmailTemplateSeedCommand($action));

        // Act
        $exitCode = $tester->execute([]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertStringNotContainsString('Wording typed into the admin UI is lost', $tester->getDisplay());
    }

    public function testEverySeededRowIsListedBeforeTheTotals(): void
    {
        // Arrange
        $action = $this->createStub(TemplateSeedAction::class);
        $action->method('seed')->willReturn(self::SEEDED);
        $tester = new CommandTester(new EmailTemplateSeedCommand($action));

        // Act
        $tester->execute([]);

        // Assert
        $display = $tester->getDisplay();
        static::assertStringContainsString('Created template "welcome".', $display);
        static::assertStringContainsString('Created translation for welcome (en).', $display);
        static::assertStringContainsString('Overwrote translation for digest (de).', $display);
        static::assertStringContainsString('Done. Created 1 templates, 1 translations, overwrote 1 translations.', $display);
    }

    public function testTheFlagOverwritesAndWarnsThatAdminWordingIsLost(): void
    {
        // Arrange
        $action = $this->createMock(TemplateSeedAction::class);
        $action->expects($this->once())->method('seed')->with(true)->willReturn(self::SEEDED);
        $tester = new CommandTester(new EmailTemplateSeedCommand($action));

        // Act
        $tester->execute(['--overwrite' => true]);

        // Assert
        static::assertStringContainsString('Wording typed into the admin UI is lost', $tester->getDisplay());
    }
}
