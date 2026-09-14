<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\ImportInspectCommand;
use App\Portability\ArchiveReader;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ImportInspectCommandTest extends TestCase
{
    public function testTheDescriptionIsPrintedAsJson(): void
    {
        // Arrange
        $description = $this->description('weiqi-club', 'Weiqi Club', 'Go in Berlin', ['books']);
        $reader = $this->createStub(ArchiveReader::class);
        $reader->method('describe')->willReturn($description);
        $tester = new CommandTester(new ImportInspectCommand($reader));

        // Act
        $exitCode = $tester->execute(['archives' => ['src/DataImportFixtures/weiqi-club']]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertSame($description, json_decode($tester->getDisplay(), true, 512, JSON_THROW_ON_ERROR));
    }

    public function testThePluginsFormatPrintsTheKeysOnOneLine(): void
    {
        // Arrange
        $reader = $this->createStub(ArchiveReader::class);
        $reader->method('describe')->willReturn($this->description('supper', 'Supper Club', '', ['dishes', 'wishlist']));
        $tester = new CommandTester(new ImportInspectCommand($reader));

        // Act
        $exitCode = $tester->execute(['archives' => ['src/DataImportFixtures/supper'], '--format' => 'plugins']);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertSame("dishes wishlist\n", $tester->getDisplay());
    }

    public function testTheTableFormatListsEveryArchiveByItsDirectoryName(): void
    {
        // Arrange
        $reader = $this->createStub(ArchiveReader::class);
        $reader->method('describe')->willReturnCallback(fn(string $archive): array => match ($archive) {
            'src/DataImportFixtures/weiqi-club/' => $this->description('weiqi-club', 'Weiqi Club', 'Go in Berlin', ['films']),
            default => $this->description('vanilla-group', 'Vanilla Group', 'Nothing but the install', []),
        });
        $tester = new CommandTester(new ImportInspectCommand($reader));

        // Act
        $exitCode = $tester->execute([
            'archives' => ['src/DataImportFixtures/weiqi-club/', 'src/DataImportFixtures/vanilla-group/'],
            '--format' => 'table',
        ]);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
        static::assertMatchesRegularExpression('/weiqi-club\s+Weiqi Club\s+films\s+Go in Berlin/', $tester->getDisplay());
        static::assertMatchesRegularExpression('/vanilla-group\s+Vanilla Group\s+Nothing but the install/', $tester->getDisplay());
    }

    public function testTheJsonFormatRefusesSeveralArchives(): void
    {
        // Arrange
        $reader = $this->createMock(ArchiveReader::class);
        $reader->expects($this->never())->method('describe');
        $tester = new CommandTester(new ImportInspectCommand($reader));

        // Act
        $exitCode = $tester->execute(['archives' => ['a', 'b']], ['capture_stderr_separately' => true]);

        // Assert
        static::assertSame(Command::INVALID, $exitCode);
        static::assertStringContainsString('exactly one archive', $tester->getErrorOutput());
    }

    public function testAnUnknownFormatIsRefused(): void
    {
        // Arrange
        $tester = new CommandTester(new ImportInspectCommand($this->createStub(ArchiveReader::class)));

        // Act
        $exitCode = $tester->execute(['archives' => ['a'], '--format' => 'yaml'], ['capture_stderr_separately' => true]);

        // Assert
        static::assertSame(Command::INVALID, $exitCode);
        static::assertStringContainsString('yaml', $tester->getErrorOutput());
    }

    public function testAnUnreadableArchiveFailsWithTheReasonOnStderr(): void
    {
        // Arrange
        $reader = $this->createStub(ArchiveReader::class);
        $reader->method('describe')->willThrowException(new RuntimeException('Invalid export format'));
        $tester = new CommandTester(new ImportInspectCommand($reader));

        // Act
        $exitCode = $tester->execute(['archives' => ['/tmp/other.zip']], ['capture_stderr_separately' => true]);

        // Assert
        static::assertSame(Command::FAILURE, $exitCode);
        static::assertSame('', $tester->getDisplay());
        static::assertStringContainsString('Invalid export format', $tester->getErrorOutput());
    }

    /**
     * @param list<string> $plugins
     * @return array{format: string, version: string, exported_at: string, slug: string, name: string, description: string, plugins: list<string>, missing_plugins: list<string>, counts: array<string, int>}
     */
    private function description(string $slug, string $name, string $description, array $plugins): array
    {
        return [
            'format' => 'meetagain-group-export',
            'version' => '2.0',
            'exported_at' => '2026-01-05T00:00:00+01:00',
            'slug' => $slug,
            'name' => $name,
            'description' => $description,
            'plugins' => $plugins,
            'missing_plugins' => [],
            'counts' => ['users' => 2],
        ];
    }
}
