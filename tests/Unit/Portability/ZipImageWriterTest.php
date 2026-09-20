<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Entity\Image;
use App\Portability\ZipImageWriter;
use PHPUnit\Framework\TestCase;
use ZipArchive;

final class ZipImageWriterTest extends TestCase
{
    private string $projectDir = '';

    private ZipArchive $zip;

    private bool $zipOpen = false;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/zip-image-writer-' . uniqid('', true);
        mkdir($this->projectDir . '/data/images', 0o777, true);

        $this->zip = new ZipArchive();
        $this->zip->open($this->projectDir . '/export.zip', ZipArchive::CREATE);
        $this->zipOpen = true;
    }

    protected function tearDown(): void
    {
        if ($this->zipOpen) {
            $this->zip->close();
        }

        foreach (glob($this->projectDir . '/data/images/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->projectDir . '/data/images');
        rmdir($this->projectDir . '/data');
        if (file_exists($this->projectDir . '/export.zip')) {
            unlink($this->projectDir . '/export.zip');
        }
        rmdir($this->projectDir);
    }

    public function testAnImageIsStoredOnceUnderItsContentHash(): void
    {
        // Arrange
        $writer = new ZipImageWriter($this->zip, $this->projectDir);
        $first = $this->image('abc123');
        $sameContent = $this->image('abc123');

        // Act
        $firstPath = $writer->addImage($first);
        $secondPath = $writer->addImage($sameContent);

        // Assert
        static::assertSame('images/abc123.jpg', $firstPath);
        static::assertSame('images/abc123.jpg', $secondPath);
        static::assertSame(1, $this->closedZipFileCount());
    }

    public function testAnImageWithoutItsFileIsLeftOut(): void
    {
        // Arrange
        $missing = new Image()
            ->setHash('missing')
            ->setExtension('jpg');

        // Act
        $path = new ZipImageWriter($this->zip, $this->projectDir)->addImage($missing);

        // Assert
        static::assertNull($path);
    }

    public function testOnlyImagesWithAnAttributionStatementAreListed(): void
    {
        // Arrange
        $writer = new ZipImageWriter($this->zip, $this->projectDir);
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
        file_put_contents($this->projectDir . '/data/images/' . $hash . '.jpg', $hash);

        return new Image()
            ->setHash($hash)
            ->setExtension('jpg');
    }

    private function closedZipFileCount(): int
    {
        $this->zip->close();
        $this->zipOpen = false;
        $zip = new ZipArchive();
        $zip->open($this->projectDir . '/export.zip');
        $count = $zip->numFiles;
        $zip->close();

        return $count;
    }
}
