<?php declare(strict_types=1);

namespace Tests\Unit\Command;

use App\Command\ExportCommand;
use App\ExtendedFilesystem;
use App\Portability\Exporter;
use App\Portability\InstanceScopeBuilder;
use App\Portability\Scope;
use DateTimeInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

final class ExportCommandTest extends TestCase
{
    public function testTheArchiveIsWrittenToTheGivenPath(): void
    {
        // Arrange
        $exporter = $this->createStub(Exporter::class);
        $exporter->method('export')->willReturn('/tmp/meetagain-export.zip');
        $fs = $this->createMock(ExtendedFilesystem::class);
        $fs->method('getFileContents')->willReturn('zip-bytes');
        $fs->expects($this->once())->method('putFileContents')->with('/out/instance.zip', 'zip-bytes')->willReturn(true);
        $fs->expects($this->once())->method('deleteFile')->with('/tmp/meetagain-export.zip')->willReturn(true);
        $tester = new CommandTester(new ExportCommand($exporter, $this->scopeBuilder(), $fs));

        // Act
        $exitCode = $tester->execute(['archive' => '/out/instance.zip']);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testTheAnchorReachesTheExporter(): void
    {
        // Arrange
        $exporter = $this->createMock(Exporter::class);
        $exporter
            ->expects($this->once())
            ->method('export')
            ->with(static::isInstanceOf(Scope::class), static::callback(static fn(?DateTimeInterface $anchor): bool => $anchor?->format('Y-m-d') === '2026-01-07'))
            ->willReturn('/tmp/meetagain-export.zip');
        $tester = new CommandTester(new ExportCommand($exporter, $this->scopeBuilder(), $this->createStub(ExtendedFilesystem::class)));

        // Act
        $exitCode = $tester->execute(['archive' => '/out/instance.zip', '--anchor' => '2026-01-07']);

        // Assert
        static::assertSame(Command::SUCCESS, $exitCode);
    }

    public function testAMalformedAnchorIsRejectedBeforeAnythingIsExported(): void
    {
        // Arrange
        $exporter = $this->createMock(Exporter::class);
        $exporter->expects($this->never())->method('export');
        $tester = new CommandTester(new ExportCommand($exporter, $this->scopeBuilder(), $this->createStub(ExtendedFilesystem::class)));

        // Act
        $exitCode = $tester->execute(['archive' => '/out/instance.zip', '--anchor' => '07.01.2026']);

        // Assert
        static::assertSame(Command::INVALID, $exitCode);
    }

    private function scopeBuilder(): InstanceScopeBuilder
    {
        $scopeBuilder = $this->createStub(InstanceScopeBuilder::class);
        $scopeBuilder->method('build')->willReturn(new Scope());

        return $scopeBuilder;
    }
}
