<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\ExtendedFilesystem;
use App\Portability\ImageImporter;
use App\Service\Media\MetadataStripper;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\UnitOfWork;
use Imagick;
use ImagickPixel;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;

class ImageImporterTest extends TestCase
{
    private const string GPS_ROTATED_FIXTURE = __DIR__ . '/../../fixtures/gps-rotated.jpg';

    private Filesystem $images;

    protected function setUp(): void
    {
        $this->images = new Filesystem(new InMemoryFilesystemAdapter());
    }

    public function testReturnsNullWhenSourcePathDoesNotExist(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(false);
        $importer = $this->buildImporter($fs);

        // Act
        $result = $importer->import('/missing.png', ImageType::EventUpload, new User());

        // Assert
        static::assertNull($result);
    }

    public function testReturnsExistingImageWhenHashAlreadyPersisted(): void
    {
        // Arrange
        $existing = new Image();
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn('fake-image-bytes');

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn($existing);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($imageRepo);

        $importer = $this->buildImporter($fs, $em);

        // Act
        $result = $importer->import('/source.png', ImageType::EventUpload, new User());

        // Assert
        static::assertSame($existing, $result);
    }

    public function testReusesAnImageAnEarlierRowOfTheSameImportCreated(): void
    {
        // Arrange
        $bytes = 'shared-avatar-bytes';
        $pending = new Image();
        $pending->setHash(sha1($bytes));

        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn($bytes);

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn([new User(), $pending]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($imageRepo);
        $em->method('getUnitOfWork')->willReturn($unitOfWork);

        $importer = $this->buildImporter($fs, $em);

        // Act
        $result = $importer->import('/users/second-copy.png', ImageType::ProfilePicture, new User());

        // Assert
        static::assertSame($pending, $result);
        static::assertSame([], iterator_to_array($this->images->listContents('')));
    }

    public function testReturnsNullWhenExtensionIsEmpty(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn('bytes');

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($imageRepo);

        $importer = $this->buildImporter($fs, $em);

        // Act
        $result = $importer->import('/source-no-extension', ImageType::EventUpload, new User());

        // Assert
        static::assertNull($result);
    }

    public function testPersistsAndWritesNewImage(): void
    {
        // Arrange
        $bytes = $this->pngBytes();
        $expectedHash = sha1($bytes);
        $persisted = null;

        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn($bytes);

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($imageRepo);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            if ($entity instanceof Image) {
                $persisted = $entity;
            }
        });

        $importer = $this->buildImporter($fs, $em);

        // Act
        $result = $importer->import('/source.png', ImageType::EventUpload, new User());

        // Assert
        static::assertNotNull($result);
        static::assertSame($expectedHash, $result->getHash());
        static::assertSame('png', $result->getExtension());
        static::assertSame(strlen($bytes), $result->getSize());
        static::assertSame($result, $persisted);
        static::assertSame($bytes, $this->images->read($expectedHash . '.png'));
    }

    public function testANewImageCarriesTheArchivedAttribution(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn($this->pngBytes());

        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn([]);

        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($imageRepo);
        $em->method('getUnitOfWork')->willReturn($unitOfWork);

        // Act
        $image = $this->buildImporter($fs, $em)->import('/images/abc.jpg', ImageType::PluginPhotosPhoto, new User(), 'Photo: Ada, CC BY 4.0', true);

        // Assert
        static::assertSame('Photo: Ada, CC BY 4.0', $image?->getAttribution());
        static::assertTrue($image?->isAttributionNotRequired());
    }

    public function testAnArchivedFileNamedByItsHashKeepsThatHashAndIsStoredWithoutLocation(): void
    {
        // Arrange
        $hash = str_repeat('ab', 20);
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn((string) file_get_contents(self::GPS_ROTATED_FIXTURE));
        $importer = $this->buildImporter($fs, $this->emptyEntityManager());

        // Act
        $image = $importer->import('/images/' . $hash . '.jpg', ImageType::PluginPhotosPhoto, new User());

        // Assert
        $stored = new Imagick();
        $stored->readImageBlob($this->images->read($hash . '.jpg'));
        static::assertSame($hash, $image?->getHash());
        static::assertSame([], $stored->getImageProperties('exif:GPS*'));
        static::assertSame([20, 40], [$stored->getImageWidth(), $stored->getImageHeight()]);
    }

    public function testAFileImagickCannotDecodeIsSkipped(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn('not an image at all');
        $importer = $this->buildImporter($fs, $this->emptyEntityManager());

        // Act
        $image = $importer->import('/images/broken.jpg', ImageType::EventUpload, new User());

        // Assert
        static::assertNull($image);
        static::assertSame([], iterator_to_array($this->images->listContents('')));
    }

    private function buildImporter(ExtendedFilesystem $fs, ?EntityManagerInterface $em = null): ImageImporter
    {
        return new ImageImporter(
            em: $em ?? $this->createStub(EntityManagerInterface::class),
            fs: $fs,
            imageStorage: $this->images,
            metadataStripper: new MetadataStripper(),
            logger: $this->createStub(LoggerInterface::class),
        );
    }

    private function emptyEntityManager(): EntityManagerInterface
    {
        $imageRepo = $this->createStub(EntityRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getScheduledEntityInsertions')->willReturn([]);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getRepository')->willReturn($imageRepo);
        $em->method('getUnitOfWork')->willReturn($unitOfWork);

        return $em;
    }

    private function pngBytes(): string
    {
        $imagick = new Imagick();
        $imagick->newImage(4, 4, new ImagickPixel('green'));
        $imagick->setImageFormat('png');

        return $imagick->getImageBlob();
    }
}
