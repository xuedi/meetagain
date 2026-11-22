<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Entity\User;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Repository\ImageRepository;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use App\Service\Media\ThumbnailSizeFormat;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

class ImageServicePixelBoundTest extends TestCase
{
    /** @var list<string> */
    private array $files = [];

    protected function tearDown(): void
    {
        foreach ($this->files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
    }

    public function testUploadRefusesAnImageAbovePixelBoundBeforeStoring(): void
    {
        // Arrange
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $uploadedFile = new UploadedFile($this->write($this->solidPng(8000, 8000)), 'big.png', 'image/png', null, true);
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);
        $subject = $this->createService($entityManager);

        // Act & Assert
        $this->expectException(RuntimeException::class);
        $subject->upload($uploadedFile, $user, ImageType::ProfilePicture);
    }

    public function testExceedsPixelBoundIsTrueForAnEdgePastTheLimitAlone(): void
    {
        // Arrange
        $subject = $this->createService($this->createStub(EntityManagerInterface::class));
        $wide = $this->write($this->solidPng(ImageService::MAX_EDGE + 1000, 1000));
        $tall = $this->write($this->solidPng(1000, ImageService::MAX_EDGE + 1000));

        // Act & Assert
        $this->assertTrue($subject->exceedsPixelBound($wide));
        $this->assertTrue($subject->exceedsPixelBound($tall));
    }

    public function testExceedsPixelBoundIsFalseForSmallAndUnreadableFiles(): void
    {
        // Arrange
        $subject = $this->createService($this->createStub(EntityManagerInterface::class));
        $small = $this->write($this->solidPng(10, 10));
        $junk = $this->write('not an image');

        // Act & Assert
        $this->assertFalse($subject->exceedsPixelBound($small));
        $this->assertFalse($subject->exceedsPixelBound($junk));
    }

    private function createService(EntityManagerInterface $entityManager): ImageService
    {
        return new ImageService(
            $this->createStub(ImageRepository::class),
            $entityManager,
            $this->createStub(ImageTypeRegistry::class),
            new ThumbnailSizeFormat(),
            new Filesystem(new InMemoryFilesystemAdapter()),
            new Filesystem(new InMemoryFilesystemAdapter()),
            $this->createStub(LoggerInterface::class),
            $this->createStub(ImageLocationService::class),
            $this->createStub(ImageLocationRepository::class),
            new LockFactory(new InMemoryStore()),
        );
    }

    private function write(string $content): string
    {
        $path = (string) tempnam(sys_get_temp_dir(), 'pixel-bound-');
        file_put_contents($path, $content);
        $this->files[] = $path;

        return $path;
    }

    private function solidPng(int $width, int $height): string
    {
        $header = pack('NNCCCCC', $width, $height, 8, 0, 0, 0, 0);
        $row = str_repeat("\x00", $width + 1);
        $deflate = deflate_init(ZLIB_ENCODING_DEFLATE);
        $data = '';
        for ($y = 0; $y < $height; ++$y) {
            $data .= deflate_add($deflate, $row, ZLIB_NO_FLUSH);
        }
        $data .= deflate_add($deflate, '', ZLIB_FINISH);

        return (
            "\x89PNG\r\n\x1a\n"
            . pack('N', 13)
            . 'IHDR'
            . $header
            . pack('N', crc32('IHDR' . $header))
            . pack('N', strlen($data))
            . 'IDAT'
            . $data
            . pack('N', crc32('IDAT' . $data))
            . pack('N', 0)
            . 'IEND'
            . pack('N', crc32('IEND'))
        );
    }
}
