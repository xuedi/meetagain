<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\ImportCommand;
use App\Portability\Importer;
use App\Portability\ImportSummary;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportCommandTest extends TestCase
{
    public function testTheOptionsReachTheImporter(): void
    {
        // Arrange
        $importer = $this->createMock(Importer::class);
        $importer->expects($this->once())->method('import')->with('/tmp/weiqi.zip', true, true)->willReturn(new ImportSummary());
        $tester = new CommandTester(new ImportCommand($importer));

        // Act
        $exitCode = $tester->execute(['archive' => '/tmp/weiqi.zip', '--shift-dates' => true, '--site-settings' => true]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    /**
     * @return iterable<string, array{bool, int}>
     */
    public static function strictProvider(): iterable
    {
        yield 'a lenient import reports and succeeds' => [false, Command::SUCCESS];
        yield 'a strict import fails' => [true, Command::FAILURE];
    }

    #[DataProvider('strictProvider')]
    public function testSkippedRowsFailOnlyAStrictImport(bool $strict, int $expectedExitCode): void
    {
        // Arrange
        $summary = new ImportSummary(['cms_pages' => ['created' => 1, 'skipped' => 2]]);
        $tester = new CommandTester(new ImportCommand($this->importer($summary)));

        // Act
        $exitCode = $tester->execute(['archive' => '/tmp/weiqi.zip', '--strict' => $strict]);

        // Assert
        static::assertSame($expectedExitCode, $exitCode);
        static::assertStringContainsString('cms_pages', $tester->getDisplay());
    }

    public function testAStrictImportThatLostNothingSucceeds(): void
    {
        // Arrange
        $summary = new ImportSummary(['users' => ['created' => 3, 'matched' => 1]]);
        $tester = new CommandTester(new ImportCommand($this->importer($summary)));

        // Act
        $exitCode = $tester->execute(['archive' => '/tmp/weiqi.zip', '--strict' => true]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testPluginsThisInstanceLacksAreNamed(): void
    {
        // Arrange
        $tester = new CommandTester(new ImportCommand($this->importer(new ImportSummary(missingPlugins: ['films']))));

        // Act
        $tester->execute(['archive' => '/tmp/weiqi.zip']);

        // Assert
        static::assertStringContainsString('films', $tester->getDisplay());
    }

    public function testAnUnreadableArchiveFails(): void
    {
        // Arrange
        $importer = $this->createStub(Importer::class);
        $importer->method('import')->willThrowException(new RuntimeException('Invalid export format'));
        $tester = new CommandTester(new ImportCommand($importer));

        // Act
        $exitCode = $tester->execute(['archive' => '/tmp/weiqi.zip']);

        // Assert
        static::assertSame(Command::FAILURE, $exitCode);
        static::assertStringContainsString('Invalid export format', $tester->getDisplay());
    }

    private function importer(ImportSummary $summary): Importer
    {
        $importer = $this->createStub(Importer::class);
        $importer->method('import')->willReturn($summary);

        return $importer;
    }
}
