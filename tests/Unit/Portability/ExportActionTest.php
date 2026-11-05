<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Portability\ExportAction;
use App\Portability\Exporter;
use App\Portability\InstanceScopeBuilder;
use App\Portability\Scope;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use Module\Task\Contract\Status;
use PHPUnit\Framework\TestCase;

class ExportActionTest extends TestCase
{
    public function testRunWritesTheArchiveIntoThePrivateStorageAndRemovesTheTempFile(): void
    {
        // Arrange
        $zipPath = (string) tempnam(sys_get_temp_dir(), 'export-action-test-');
        file_put_contents($zipPath, str_repeat('x', 2_097_152));
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $subject = $this->createAction($zipPath, $storage);

        // Act
        $result = $subject->run([]);

        // Assert
        static::assertSame(Status::ok, $result->status);
        static::assertStringContainsString('2.0 MB', $result->message);
        static::assertSame(2_097_152, $storage->fileSize(ExportAction::ARCHIVE));
        static::assertFileDoesNotExist($zipPath);
    }

    public function testANewRunReplacesThePreviousArchive(): void
    {
        // Arrange
        $zipPath = (string) tempnam(sys_get_temp_dir(), 'export-action-test-');
        file_put_contents($zipPath, 'new');
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $storage->write(ExportAction::ARCHIVE, 'the previous archive');
        $subject = $this->createAction($zipPath, $storage);

        // Act
        $subject->run([]);

        // Assert
        static::assertSame('new', $storage->read(ExportAction::ARCHIVE));
    }

    public function testFactsAndArchiveAreEmptyWithoutAnArchive(): void
    {
        // Arrange
        $subject = $this->createAction('/nonexistent', new Filesystem(new InMemoryFilesystemAdapter()));

        // Act & Assert
        static::assertSame([], $subject->getFacts());
        static::assertNull($subject->archive());
    }

    public function testFactsNameTheArchiveDateAndSize(): void
    {
        // Arrange
        $storage = new Filesystem(new InMemoryFilesystemAdapter());
        $storage->write(ExportAction::ARCHIVE, str_repeat('x', 1_048_576));
        $subject = $this->createAction('/nonexistent', $storage);

        // Act
        $facts = $subject->getFacts();

        // Assert
        static::assertSame('1.0 MB', $facts['admin_system_import.export_fact_size']);
        static::assertArrayHasKey('admin_system_import.export_fact_written', $facts);
    }

    private function createAction(string $zipPath, Filesystem $storage): ExportAction
    {
        $exporter = $this->createStub(Exporter::class);
        $exporter->method('export')->willReturn($zipPath);
        $scopeBuilder = $this->createStub(InstanceScopeBuilder::class);
        $scopeBuilder->method('build')->willReturn(new Scope());

        return new ExportAction($exporter, $scopeBuilder, $storage);
    }
}
