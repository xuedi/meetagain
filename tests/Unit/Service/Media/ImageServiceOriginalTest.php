<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Repository\ImageRepository;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use App\Service\Media\MetadataStripper;
use App\Service\Media\ThumbnailSizeFormat;
use Doctrine\ORM\EntityManagerInterface;
use Imagick;
use League\Flysystem\Filesystem;
use League\Flysystem\FilesystemOperator;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

final class ImageServiceOriginalTest extends TestCase
{
    private const string GPS_ROTATED_FIXTURE = __DIR__ . '/../../../fixtures/gps-rotated.jpg';

    private FilesystemOperator $imageStorage;

    protected function setUp(): void
    {
        $this->imageStorage = new Filesystem(new InMemoryFilesystemAdapter());
    }

    public function testUploadStoresTheOriginalWithoutLocationAndUpright(): void
    {
        // Arrange
        $raw = (string) file_get_contents(self::GPS_ROTATED_FIXTURE);
        $subject = $this->createService(imageRepo: $this->emptyImageRepo());

        // Act
        $image = $subject->upload($this->jpegUpload($raw), $this->storedUser(), ImageType::PluginPhotosPhoto);

        // Assert
        $stored = new Imagick();
        $stored->readImageBlob($this->imageStorage->read(sha1($raw) . '.jpg'));
        static::assertSame(sha1($raw), $image?->getHash());
        static::assertSame([], $stored->getImageProperties('exif:GPS*'));
        static::assertSame([], $stored->getImageProfiles('*', false));
        static::assertSame([20, 40], [$stored->getImageWidth(), $stored->getImageHeight()]);
        static::assertContains($stored->getImageOrientation(), [Imagick::ORIENTATION_UNDEFINED, Imagick::ORIENTATION_TOPLEFT]);
        static::assertGreaterThan(0.5, $stored->getImagePixelColor(10, 5)->getColorValue(Imagick::COLOR_RED));
        static::assertSame(strlen($this->imageStorage->read(sha1($raw) . '.jpg')), $image?->getSize());
    }

    public function testUploadKeepsTheColourProfileOfAStrippedOriginal(): void
    {
        // Arrange
        $source = new Imagick(self::GPS_ROTATED_FIXTURE);
        $source->setImageProfile('icc', str_repeat("\x01", 200));
        $raw = $source->getImageBlob();
        $subject = $this->createService(imageRepo: $this->emptyImageRepo());

        // Act
        $subject->upload($this->jpegUpload($raw), $this->storedUser(), ImageType::PluginPhotosPhoto);

        // Assert
        $stored = new Imagick();
        $stored->readImageBlob($this->imageStorage->read(sha1($raw) . '.jpg'));
        static::assertSame(['icc'], array_values($stored->getImageProfiles('*', false)));
    }

    public function testAnUploadImagickCannotDecodeIsRefused(): void
    {
        // Arrange
        $subject = $this->createService(imageRepo: $this->emptyImageRepo());

        // Act + Assert
        $this->expectException(RuntimeException::class);
        try {
            $subject->upload($this->jpegUpload('not an image at all'), $this->storedUser(), ImageType::ProfilePicture);
        } finally {
            static::assertSame([], $this->imageStorage->listContents('', true)->toArray());
        }
    }

    public function testStripOriginalMetadataRewritesTheStoredFileOnceAndKeepsItsKey(): void
    {
        // Arrange
        $raw = (string) file_get_contents(self::GPS_ROTATED_FIXTURE);
        $this->imageStorage->write('abc.jpg', $raw);
        $image = new Image();
        $image->setHash('abc');
        $image->setExtension('jpg');
        $subject = $this->createService();

        // Act
        $first = $subject->stripOriginalMetadata($image);
        $second = $subject->stripOriginalMetadata($image);

        // Assert
        static::assertTrue($first);
        static::assertFalse($second);
        static::assertNotSame($raw, $this->imageStorage->read('abc.jpg'));
        static::assertSame(strlen($this->imageStorage->read('abc.jpg')), $image->getSize());
    }

    public function testRenderPreviewServesNothingWhenTheOriginalCannotBeRendered(): void
    {
        // Arrange
        $this->imageStorage->write('broken.png', 'not an image at all');
        $subject = $this->createService();

        // Act
        $preview = $subject->renderPreview($this->storedImage('broken', 'png'));

        // Assert
        static::assertNull($preview);
    }

    private function createService(?ImageRepository $imageRepo = null): ImageService
    {
        return new ImageService(
            $imageRepo ?? $this->createStub(ImageRepository::class),
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(ImageTypeRegistry::class),
            new ThumbnailSizeFormat(),
            $this->imageStorage,
            new Filesystem(new InMemoryFilesystemAdapter()),
            $this->createStub(LoggerInterface::class),
            $this->createStub(ImageLocationService::class),
            $this->createStub(ImageLocationRepository::class),
            new LockFactory(new InMemoryStore()),
            new MetadataStripper(),
        );
    }

    private function jpegUpload(string $content): UploadedFile
    {
        $uploadedFile = $this->createStub(UploadedFile::class);
        $uploadedFile->method('getContent')->willReturn($content);
        $uploadedFile->method('getMimeType')->willReturn('image/jpeg');
        $uploadedFile->method('guessExtension')->willReturn('jpg');
        $uploadedFile->method('getSize')->willReturn(strlen($content));

        return $uploadedFile;
    }

    private function emptyImageRepo(): ImageRepository
    {
        $imageRepo = $this->createStub(ImageRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);

        return $imageRepo;
    }

    private function storedImage(string $hash, string $extension): Image
    {
        $image = new Image();
        $image->setHash($hash);
        $image->setExtension($extension);

        return $image;
    }

    private function storedUser(): User
    {
        $user = $this->createStub(User::class);
        $user->method('getId')->willReturn(1);

        return $user;
    }
}
