<?php declare(strict_types=1);

namespace Tests\Unit\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageType;
use App\ExtendedFilesystem;
use App\Portability\ImageImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;

class ImageImporterTest extends TestCase
{
    private const string PROJECT_DIR = '/app';

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
        $written = false;

        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn($bytes);
        $fs->method('putFileContents')->willReturnCallback(static function () use (&$written): bool {
            $written = true;
            return true;
        });

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
        static::assertFalse($written);
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
        $bytes = 'png-bytes';
        $expectedHash = sha1($bytes);
        $writtenPath = null;
        $persisted = null;

        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn($bytes);
        $fs->method('isDirectory')->willReturn(true);
        $fs->method('putFileContents')->willReturnCallback(static function (string $path) use (&$writtenPath): bool {
            $writtenPath = $path;
            return true;
        });

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
        static::assertSame(self::PROJECT_DIR . '/data/images/' . $expectedHash . '.png', $writtenPath);
    }

    public function testANewImageCarriesTheArchivedAttribution(): void
    {
        // Arrange
        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);
        $fs->method('getFileContents')->willReturn('photo-bytes');
        $fs->method('isDirectory')->willReturn(true);

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

    private function buildImporter(ExtendedFilesystem $fs, ?EntityManagerInterface $em = null): ImageImporter
    {
        return new ImageImporter(em: $em ?? $this->createStub(EntityManagerInterface::class), fs: $fs, projectDir: self::PROJECT_DIR);
    }
}
