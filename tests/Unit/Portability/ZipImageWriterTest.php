<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Entity\Image;
use App\Portability\ZipImageWriter;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ZipImageWriterTest extends TestCase
{
    private string $directory = '';

    private Filesystem $images;

    private ZipArchive $zip;

    private bool $zipOpen = false;

    protected function setUp(): void
    {
        $this->directory = sys_get_temp_dir() . '/zip-image-writer-' . uniqid('', true);
        mkdir($this->directory, 0o777, true);
        $this->images = new Filesystem(new InMemoryFilesystemAdapter());

        $this->zip = new ZipArchive();
        $this->zip->open($this->directory . '/export.zip', ZipArchive::CREATE);
        $this->zipOpen = true;
    }

    protected function tearDown(): void
    {
        if ($this->zipOpen) {
            $this->zip->close();
        }

        if (file_exists($this->directory . '/export.zip')) {
            unlink($this->directory . '/export.zip');
        }
        rmdir($this->directory);
    }

    public function testAnImageIsStoredOnceUnderItsContentHash(): void
    {
        // Arrange
        $writer = new ZipImageWriter($this->zip, $this->images);
        $first = $this->image('abc123');
        $sameContent = $this->image('abc123');

        // Act
        $firstPath = $writer->addImage($first);
        $secondPath = $writer->addImage($sameContent);

        // Assert
        static::assertSame('images/abc123.jpg', $firstPath);
        static::assertSame('images/abc123.jpg', $secondPath);
        static::assertSame(1, $this->closedZipFileCount());
        $writer->removeCopies();
    }

    public function testTheCopiesHandedToTheArchiveAreRemovedAfterItIsClosed(): void
    {
        // Arrange
        $writer = new ZipImageWriter($this->zip, $this->images);
        $writer->addImage($this->image('abc123'));
        $this->closedZipFileCount();
        $before = glob(sys_get_temp_dir() . '/meetagain-export-image-*') ?: [];

        // Act
        $writer->removeCopies();

        // Assert
        $after = glob(sys_get_temp_dir() . '/meetagain-export-image-*') ?: [];
        static::assertCount(count($before) - 1, $after);
    }

    public function testAnImageWithoutItsFileIsLeftOut(): void
    {
        // Arrange
        $missing = new Image()
            ->setHash('missing')
            ->setExtension('jpg');

        // Act
        $path = new ZipImageWriter($this->zip, $this->images)->addImage($missing);

        // Assert
        static::assertNull($path);
    }

    public function testOnlyImagesWithAnAttributionStatementAreListed(): void
    {
        // Arrange
        $writer = new ZipImageWriter($this->zip, $this->images);
        $writer->addImage($this->image('ccc')->setAttributionNotRequired(true));
        $writer->addImage($this->image('bbb'));
        $writer->addImage($this->image('aaa')->setAttribution('Photo: Ada, CC BY 4.0'));

        // Act
        $attributions = $writer->getAttributions();

        // Assert
        static::assertSame(
            [
                'images/aaa.jpg' => ['attribution' => 'Photo: Ada, CC BY 4.0', 'attribution_not_required' => false],
                'images/ccc.jpg' => ['attribution' => null, 'attribution_not_required' => true],
            ],
            $attributions,
        );
    }

    private function image(string $hash): Image
    {
        $this->images->write($hash . '.jpg', $hash);

        return new Image()
            ->setHash($hash)
            ->setExtension('jpg');
    }

    private function closedZipFileCount(): int
    {
        $this->zip->close();
        $this->zipOpen = false;
        $zip = new ZipArchive();
        $zip->open($this->directory . '/export.zip');
        $count = $zip->numFiles;
        $zip->close();

        return $count;
    }
}
