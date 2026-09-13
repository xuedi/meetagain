<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\ExtendedFilesystem;
use App\Portability\ArchiveReader;
use App\Portability\Exporter;
use App\Service\Config\PluginService;
use PHPUnit\Framework\TestCase;
use RuntimeException;
use ZipArchive;

final class ArchiveReaderTest extends TestCase
{
    private string $directory = '';

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/archive-reader-' . uniqid('', true);
        mkdir($this->directory);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->directory . '/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->directory);
    }

    public function testTheDescriptionNamesTheSiteThePluginsAndTheRowsPerKind(): void
    {
        // Arrange
        $zipPath = $this->zip([
            'format' => Exporter::FORMAT,
            'version' => '2.0',
            'exported_at' => '2026-01-05T00:00:00+01:00',
            'plugins' => ['books', 'films'],
            'site' => ['slug' => 'weiqi-club', 'name' => 'Weiqi Club', 'description' => 'Go in Berlin'],
            'users' => [['email' => 'ada@example.org'], ['email' => 'bo@example.org']],
            'events' => [],
            'items' => ['book' => ['rows' => [['ref' => 1], ['ref' => 2], ['ref' => 3]], 'tags' => []]],
            'images' => ['images/a.jpg' => ['attribution' => 'CC BY']],
        ]);

        // Act
        $description = $this->reader(activePlugins: ['books'])->describe($zipPath);

        // Assert
        static::assertSame([
            'format' => Exporter::FORMAT,
            'version' => '2.0',
            'exported_at' => '2026-01-05T00:00:00+01:00',
            'slug' => 'weiqi-club',
            'name' => 'Weiqi Club',
            'description' => 'Go in Berlin',
            'plugins' => ['books', 'films'],
            'missing_plugins' => ['films'],
            'counts' => ['users' => 2, 'book' => 3],
        ], $description);
    }

    public function testASectionOfListsCountsEachListAsItsOwnKind(): void
    {
        // Arrange
        $zipPath = $this->zip([
            'format' => Exporter::FORMAT,
            'circulation' => ['copies' => [['ref' => 1], ['ref' => 2]], 'requests' => [], 'ledger' => [['entry_type' => 'donated']]],
        ]);

        // Act
        $description = $this->reader()->describe($zipPath);

        // Assert
        static::assertSame(['circulation_copies' => 2, 'circulation_ledger' => 1], $description['counts']);
    }

    public function testAnUnpackedArchiveReadsLikeItsZip(): void
    {
        // Arrange
        file_put_contents($this->directory . '/export.json', json_encode(['format' => Exporter::FORMAT, 'site' => ['name' => 'Vanilla Group']], JSON_THROW_ON_ERROR));

        // Act
        $description = $this->reader()->describe($this->directory);

        // Assert
        static::assertSame('Vanilla Group', $description['name']);
    }

    public function testAVersionOneArchiveIsNamedAfterItsSourceGroup(): void
    {
        // Arrange
        $zipPath = $this->zip(['format' => Exporter::FORMAT, 'version' => '1.2', 'source_group' => ['slug' => 'weiqi', 'name' => 'Weiqi Club']]);

        // Act
        $description = $this->reader()->describe($zipPath);

        // Assert
        static::assertSame('weiqi', $description['slug']);
        static::assertSame('Weiqi Club', $description['name']);
        static::assertSame([], $description['counts']);
    }

    public function testAnArchiveOfAnotherFormatIsRejected(): void
    {
        // Arrange
        $zipPath = $this->zip(['format' => 'something-else']);

        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Invalid export format');

        // Act
        $this->reader()->read($zipPath);
    }

    public function testADirectoryWithoutExportJsonIsRejected(): void
    {
        // Assert
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('export.json not found');

        // Act
        $this->reader()->read($this->directory);
    }

    /**
     * @param list<string> $activePlugins
     */
    private function reader(array $activePlugins = []): ArchiveReader
    {
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('isDirectory')->willReturnCallback(is_dir(...));
        $fs->method('fileExists')->willReturnCallback(file_exists(...));
        $fs->method('getFileContents')->willReturnCallback(file_get_contents(...));

        $pluginService = $this->createStub(PluginService::class);
        $pluginService->method('getActiveList')->willReturn($activePlugins);

        return new ArchiveReader($fs, $pluginService);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function zip(array $data): string
    {
        $path = $this->directory . '/archive.zip';
        $zip = new ZipArchive();
        $zip->open($path, ZipArchive::CREATE);
        $zip->addFromString('export.json', json_encode($data, JSON_THROW_ON_ERROR));
        $zip->close();

        return $path;
    }
}
