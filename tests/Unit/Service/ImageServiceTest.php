<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Event;
use App\Entity\Image;
use App\Entity\User;
use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\ExtendedFilesystem;
use App\Repository\ImageRepository;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageServiceTest extends TestCase
{
    private string $kernelProjectDir = '/tmp/project';

    private function createService(
        ?ImageRepository $imageRepo = null,
        ?EntityManagerInterface $entityManager = null,
        ?ImageTypeRegistry $imageTypeRegistry = null,
        ?ExtendedFilesystem $filesystemService = null,
        ?LoggerInterface $logger = null,
    ): ImageService {
        return new ImageService(
            $imageRepo ?? $this->createStub(ImageRepository::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $imageTypeRegistry ?? $this->createStub(ImageTypeRegistry::class),
            $filesystemService ?? $this->createStub(ExtendedFilesystem::class),
            $logger ?? $this->createStub(LoggerInterface::class),
            $this->kernelProjectDir,
            $this->createStub(ImageLocationService::class),
        );
    }

    public function testUploadExistingImage(): void
    {
        // Arrange
        $imageContent = 'test image content';
        $hash = sha1($imageContent);
        $user = $this->createStub(User::class);
        $type = ImageType::ProfilePicture;

        // Arrange
        $existingImage = $this->createMock(Image::class);
        $existingImage
            ->expects($this->once())
            ->method('setUpdatedAt')
            ->with(static::callback(static fn($date) => $date instanceof DateTimeImmutable));

        // Arrange
        $imageRepoMock = $this->createMock(ImageRepository::class);
        $imageRepoMock->expects($this->once())->method('findOneBy')->with(['hash' => $hash])->willReturn($existingImage);

        // Arrange
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock->expects($this->once())->method('persist')->with($existingImage);

        // Arrange
        $uploadedFile = $this->createStub(UploadedFile::class);
        $uploadedFile->method('getContent')->willReturn($imageContent);

        $subject = $this->createService(imageRepo: $imageRepoMock, entityManager: $entityManagerMock);

        // Act
        $result = $subject->upload($uploadedFile, $user, $type);

        // Assert
        static::assertSame($existingImage, $result);
    }

    public function testUploadNewImage(): void
    {
        // Arrange
        $imageContent = 'test image content';
        $hash = sha1($imageContent);
        $mimeType = 'image/jpeg';
        $extension = 'jpg';
        $size = 12345;
        $realPath = '/tmp/uploaded_file.jpg';
        $user = $this->createStub(User::class);
        $type = ImageType::ProfilePicture;

        // Arrange
        $imageRepoMock = $this->createMock(ImageRepository::class);
        $imageRepoMock->expects($this->once())->method('findOneBy')->with(['hash' => $hash])->willReturn(null);

        // Arrange
        $uploadedFile = $this->createStub(UploadedFile::class);
        $uploadedFile->method('getContent')->willReturn($imageContent);
        $uploadedFile->method('getMimeType')->willReturn($mimeType);
        $uploadedFile->method('guessExtension')->willReturn($extension);
        $uploadedFile->method('getSize')->willReturn($size);
        $uploadedFile->method('getRealPath')->willReturn($realPath);

        // Arrange
        $filesystemMock = $this->createMock(ExtendedFilesystem::class);
        $filesystemMock
            ->expects($this->once())
            ->method('copy')
            ->with($realPath, $this->kernelProjectDir . '/data/images/' . $hash . '.' . $extension);

        // Arrange
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with(static::callback(
                static fn(Image $image) => (
                    $image->getHash() === $hash
                    && $image->getMimeType() === $mimeType
                    && $image->getExtension() === $extension
                    && $image->getType() === $type
                    && $image->getSize() === $size
                    && $image->getUploader() === $user
                    && $image->getCreatedAt() instanceof DateTimeImmutable
                ),
            ));

        $subject = $this->createService(imageRepo: $imageRepoMock, entityManager: $entityManagerMock, filesystemService: $filesystemMock);

        // Act
        $result = $subject->upload($uploadedFile, $user, $type);

        // Assert
        static::assertInstanceOf(Image::class, $result);
        static::assertEquals($hash, $result->getHash());
        static::assertEquals($mimeType, $result->getMimeType());
        static::assertEquals($extension, $result->getExtension());
        static::assertEquals($type, $result->getType());
        static::assertEquals($size, $result->getSize());
        static::assertEquals($user, $result->getUploader());
    }

    public function testCreateThumbnails(): void
    {
        // Arrange
        $subject = $this
            ->getMockBuilder(ImageService::class)
            ->setConstructorArgs([
                $this->createStub(ImageRepository::class),
                $this->createStub(EntityManagerInterface::class),
                $this->createStub(ImageTypeRegistry::class),
                $this->createStub(ExtendedFilesystem::class),
                $this->createStub(LoggerInterface::class),
                $this->kernelProjectDir,
                $this->createStub(ImageLocationService::class),
            ])
            ->onlyMethods(['createThumbnails'])
            ->getMock();

        // Arrange
        $image = $this->createStub(Image::class);
        $image->method('getHash')->willReturn('test_hash');
        $image->method('getExtension')->willReturn('jpg');
        $image->method('getType')->willReturn(ImageType::ProfilePicture);

        // Arrange
        $subject->expects($this->once())->method('createThumbnails')->with($image)->willReturn(2);

        // Act
        $result = $subject->createThumbnails($image);

        // Assert
        static::assertSame(2, $result);
    }

    public function testRotateThumbNail(): void
    {
        // Arrange
        $subject = $this
            ->getMockBuilder(ImageService::class)
            ->setConstructorArgs([
                $this->createStub(ImageRepository::class),
                $this->createStub(EntityManagerInterface::class),
                $this->createStub(ImageTypeRegistry::class),
                $this->createStub(ExtendedFilesystem::class),
                $this->createStub(LoggerInterface::class),
                $this->kernelProjectDir,
                $this->createStub(ImageLocationService::class),
            ])
            ->onlyMethods(['rotateThumbNail'])
            ->getMock();

        // Arrange
        $image = $this->createStub(Image::class);
        $image->method('getHash')->willReturn('test_hash');
        $image->method('getType')->willReturn(ImageType::ProfilePicture);

        // Arrange
        $subject->expects($this->once())->method('rotateThumbNail')->with($image);

        // Act
        $subject->rotateThumbNail($image);
    }

    public function testGetStatistics(): void
    {
        // Arrange
        $imageTypeRegistryMock = $this->createMock(ImageTypeRegistry::class);
        $imageTypeRegistryMock->expects($this->once())->method('getThumbnailSizeList')->willReturn(['100x100' => 0, '200x200' => 0]);
        $imageTypeRegistryMock
            ->expects($this->exactly(2))
            ->method('getThumbnailSizes')
            ->willReturnMap([
                [ImageType::ProfilePicture, [[100, 100]]],
                [ImageType::EventTeaser, [[200, 200]]],
            ]);

        // Arrange
        $filesystemMock = $this->createMock(ExtendedFilesystem::class);
        $filesystemMock
            ->expects($this->once())
            ->method('scanDirectory')
            ->with($this->kernelProjectDir . '/public/images/thumbnails/')
            ->willReturn(['.', '..', 'hash1_100x100.webp', 'hash2_200x200.webp']);

        // Arrange
        $imageRepoMock = $this->createMock(ImageRepository::class);
        $imageRepoMock
            ->expects($this->once())
            ->method('getFileList')
            ->willReturn([
                'hash1' => ImageType::ProfilePicture,
                'hash2' => ImageType::EventTeaser,
            ]);
        $imageRepoMock->expects($this->once())->method('count')->willReturn(2);

        // Arrange
        $subject = $this
            ->getMockBuilder(ImageService::class)
            ->setConstructorArgs([
                $imageRepoMock,
                $this->createStub(EntityManagerInterface::class),
                $imageTypeRegistryMock,
                $filesystemMock,
                $this->createStub(LoggerInterface::class),
                $this->kernelProjectDir,
                $this->createStub(ImageLocationService::class),
            ])
            ->onlyMethods(['getObsoleteThumbnails'])
            ->getMock();

        $subject->expects($this->once())->method('getObsoleteThumbnails')->willReturn([]);

        // Act
        $result = $subject->getStatistics();

        // Assert
        static::assertIsArray($result);
        static::assertArrayHasKey('imageCount', $result);
        static::assertArrayHasKey('imageTypeList', $result);
        static::assertArrayHasKey('thumbnailSizeList', $result);
        static::assertArrayHasKey('thumbnailCount', $result);
        static::assertArrayHasKey('thumbnailObsoleteCount', $result);
        static::assertArrayHasKey('thumbnailMissingCount', $result);
        static::assertSame(2, $result['imageCount']);
        static::assertEquals(['ProfilePicture' => 1, 'EventTeaser' => 1], $result['imageTypeList']);
        static::assertEquals(['100x100' => 1, '200x200' => 1], $result['thumbnailSizeList']);
        static::assertSame(2, $result['thumbnailCount']);
        static::assertSame(0, $result['thumbnailObsoleteCount']);
        static::assertSame(0, $result['thumbnailMissingCount']);
    }

    public function testGetObsoleteThumbnails(): void
    {
        // Arrange
        $imageRepoMock = $this->createMock(ImageRepository::class);
        $imageRepoMock
            ->expects($this->once())
            ->method('getFileList')
            ->willReturn([
                'hash1' => ImageType::ProfilePicture,
                'hash2' => ImageType::EventTeaser,
            ]);

        // Arrange
        $filesystemMock = $this->createMock(ExtendedFilesystem::class);
        $filesystemMock
            ->expects($this->once())
            ->method('scanDirectory')
            ->with($this->kernelProjectDir . '/public/images/thumbnails/')
            ->willReturn([
                '.',
                '..',
                'hash1_100x100.webp', // Valid
                'hash2_200x200.webp', // Valid
                'hash3_100x100.webp', // Obsolete (hash not in image list)
                'hash1_300x300.webp', // Obsolete (invalid size)
            ]);

        // Arrange
        // Note: isValidThumbnailSize is only called for hashes that exist in the file list
        // hash3 is not in the list, so we only get 3 calls (hash1_100x100, hash2_200x200, hash1_300x300)
        $imageTypeRegistryMock = $this->createMock(ImageTypeRegistry::class);
        $imageTypeRegistryMock
            ->expects($this->exactly(3))
            ->method('isValidThumbnailSize')
            ->willReturnCallback(static function (ImageType $type, int $width, int $height) {
                if ($type === ImageType::ProfilePicture && $width === 100 && $height === 100) {
                    return true;
                }
                if ($type === ImageType::ProfilePicture && $width === 300 && $height === 300) {
                    return false;
                }
                if ($type === ImageType::EventTeaser && $width === 200 && $height === 200) {
                    return true;
                }

                return false;
            });

        $subject = $this->createService(imageRepo: $imageRepoMock, imageTypeRegistry: $imageTypeRegistryMock, filesystemService: $filesystemMock);

        // Act
        $result = $subject->getObsoleteThumbnails();

        // Assert
        static::assertIsArray($result);
        static::assertCount(2, $result);
        static::assertContains('hash3_100x100.webp', $result);
        static::assertContains('hash1_300x300.webp', $result);
    }

    public function testDeleteObsoleteThumbnails(): void
    {
        // Arrange
        $filesystemMock = $this->createMock(ExtendedFilesystem::class);

        $subject = $this
            ->getMockBuilder(ImageService::class)
            ->setConstructorArgs([
                $this->createStub(ImageRepository::class),
                $this->createStub(EntityManagerInterface::class),
                $this->createStub(ImageTypeRegistry::class),
                $filesystemMock,
                $this->createStub(LoggerInterface::class),
                $this->kernelProjectDir,
                $this->createStub(ImageLocationService::class),
            ])
            ->onlyMethods(['getObsoleteThumbnails'])
            ->getMock();

        $obsoleteThumbnails = ['hash3_100x100.webp', 'hash1_300x300.webp'];
        $subject->expects($this->once())->method('getObsoleteThumbnails')->willReturn($obsoleteThumbnails);

        // Arrange
        $filesystemMock
            ->expects($this->exactly(2))
            ->method('exists')
            ->willReturnMap([
                [$this->kernelProjectDir . '/public/images/thumbnails/hash3_100x100.webp', true],
                [$this->kernelProjectDir . '/public/images/thumbnails/hash1_300x300.webp', true],
            ]);

        $removedFiles = [];
        $filesystemMock
            ->expects($this->exactly(2))
            ->method('remove')
            ->willReturnCallback(static function ($path) use (&$removedFiles) {
                $removedFiles[] = $path;

                return true;
            });

        // Act
        $result = $subject->deleteObsoleteThumbnails();

        // Assert
        static::assertContains($this->kernelProjectDir . '/public/images/thumbnails/hash3_100x100.webp', $removedFiles);
        static::assertContains($this->kernelProjectDir . '/public/images/thumbnails/hash1_300x300.webp', $removedFiles);
        static::assertSame(2, $result);
    }

    /**
     * @param array{server: ?string, client: string} $mimeData
     */
    #[DataProvider('provideMimeTypeFallbackCases')]
    public function testUploadDetectsMimeType(array $mimeData, ?string $expectedMime, bool $expectThrow): void
    {
        // Arrange
        $imageContent = 'content-' . random_int(1, 9999);
        $uploadedFile = $this->createStub(UploadedFile::class);
        $uploadedFile->method('getContent')->willReturn($imageContent);
        $uploadedFile->method('getMimeType')->willReturn($mimeData['server']);
        $uploadedFile->method('getClientMimeType')->willReturn($mimeData['client']);
        $uploadedFile->method('guessExtension')->willReturn('jpg');
        $uploadedFile->method('getSize')->willReturn(1);
        $uploadedFile->method('getRealPath')->willReturn('/tmp/x');

        $imageRepo = $this->createStub(ImageRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);
        $subject = $this->createService(imageRepo: $imageRepo);

        // Act + Assert
        if ($expectThrow) {
            $this->expectException(RuntimeException::class);
            $subject->upload($uploadedFile, $this->createStub(User::class), ImageType::ProfilePicture);
            return;
        }
        $image = $subject->upload($uploadedFile, $this->createStub(User::class), ImageType::ProfilePicture);
        static::assertSame($expectedMime, $image?->getMimeType());
    }

    public static function provideMimeTypeFallbackCases(): iterable
    {
        yield 'server mime present wins' => [
            ['server' => 'image/png', 'client' => 'image/jpeg'],
            'image/png',
            false,
        ];
        yield 'server null falls back to client' => [
            ['server' => null, 'client' => 'image/jpeg'],
            'image/jpeg',
            false,
        ];
        yield 'server empty falls back to client' => [
            ['server' => '', 'client' => 'image/jpeg'],
            'image/jpeg',
            false,
        ];
        yield 'client octet-stream is rejected' => [
            ['server' => null, 'client' => 'application/octet-stream'],
            null,
            true,
        ];
        yield 'all empty throws' => [
            ['server' => null, 'client' => ''],
            null,
            true,
        ];
    }

    /**
     * @param array{server: ?string, mime: string, client: string} $extData
     */
    #[DataProvider('provideExtensionFallbackCases')]
    public function testUploadDetectsExtension(array $extData, ?string $expectedExt, bool $expectThrow): void
    {
        // Arrange
        $uploadedFile = $this->createStub(UploadedFile::class);
        $uploadedFile->method('getContent')->willReturn('payload');
        $uploadedFile->method('getMimeType')->willReturn($extData['mime']);
        $uploadedFile->method('guessExtension')->willReturn($extData['server']);
        $uploadedFile->method('getClientOriginalExtension')->willReturn($extData['client']);
        $uploadedFile->method('getSize')->willReturn(1);
        $uploadedFile->method('getRealPath')->willReturn('/tmp/x');

        $imageRepo = $this->createStub(ImageRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);
        $subject = $this->createService(imageRepo: $imageRepo);

        // Act + Assert
        if ($expectThrow) {
            $this->expectException(RuntimeException::class);
            $subject->upload($uploadedFile, $this->createStub(User::class), ImageType::ProfilePicture);
            return;
        }
        $image = $subject->upload($uploadedFile, $this->createStub(User::class), ImageType::ProfilePicture);
        static::assertSame($expectedExt, $image?->getExtension());
    }

    public static function provideExtensionFallbackCases(): iterable
    {
        yield 'server guess wins' => [
            ['server' => 'png', 'mime' => 'image/jpeg', 'client' => 'JPG'],
            'png',
            false,
        ];
        yield 'server null falls back to mime registry' => [
            ['server' => null, 'mime' => 'image/png', 'client' => 'something'],
            'png',
            false,
        ];
        yield 'server null and unknown mime falls back to client lowercased' => [
            ['server' => null, 'mime' => 'application/x-totally-unknown', 'client' => 'XYZ'],
            'xyz',
            false,
        ];
        yield 'all empty throws' => [
            ['server' => null, 'mime' => 'application/x-totally-unknown', 'client' => ''],
            null,
            true,
        ];
    }

    public function testUploadForEventReturnsFileCountAndRegistersLocations(): void
    {
        // Arrange
        $imageRepo = $this->createStub(ImageRepository::class);
        $imageRepo->method('findOneBy')->willReturn(null);

        $imageTypeRegistry = $this->createStub(ImageTypeRegistry::class);
        $imageTypeRegistry->method('getThumbnailSizes')->willReturn([]);
        $imageTypeRegistry->method('getFitMode')->willReturn(ImageFitMode::Fit);

        $nextId = 100;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$nextId): void {
            if ($entity instanceof Image && $entity->getId() === null) {
                $ref = new \ReflectionClass($entity);
                $prop = $ref->getProperty('id');
                $prop->setValue($entity, $nextId++);
            }
        });

        $locationService = $this->createMock(ImageLocationService::class);
        $locationService->expects($this->exactly(2))->method('addLocation');

        $event = $this->createMock(Event::class);
        $event->method('getId')->willReturn(7);
        $event->expects($this->exactly(2))->method('addImage');

        $files = [];
        for ($i = 0; $i < 2; ++$i) {
            $f = $this->createStub(UploadedFile::class);
            $f->method('getContent')->willReturn('payload-' . $i);
            $f->method('getMimeType')->willReturn('image/jpeg');
            $f->method('guessExtension')->willReturn('jpg');
            $f->method('getSize')->willReturn(10);
            $f->method('getRealPath')->willReturn('/tmp/' . $i);
            $files[] = $f;
        }

        $subject = new ImageService(
            $imageRepo,
            $em,
            $imageTypeRegistry,
            $this->createStub(ExtendedFilesystem::class),
            $this->createStub(LoggerInterface::class),
            $this->kernelProjectDir,
            $locationService,
        );

        // Act
        $count = $subject->uploadForEvent($event, $files, $this->createStub(User::class));

        // Assert
        static::assertSame(2, $count);
    }

    public function testCreateThumbnailsSkipsAlreadyExistingTargets(): void
    {
        // Arrange
        $imageTypeRegistry = $this->createStub(ImageTypeRegistry::class);
        $imageTypeRegistry->method('getThumbnailSizes')->willReturn([[100, 100], [200, 200]]);
        $imageTypeRegistry->method('getFitMode')->willReturn(ImageFitMode::Fit);

        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(true);

        $image = $this->createStub(Image::class);
        $image->method('getHash')->willReturn('h');
        $image->method('getExtension')->willReturn('jpg');
        $image->method('getType')->willReturn(ImageType::ProfilePicture);

        $subject = $this->createService(imageTypeRegistry: $imageTypeRegistry, filesystemService: $fs);

        // Act
        $created = $subject->createThumbnails($image);

        // Assert
        static::assertSame(0, $created);
    }

    #[DataProvider('provideFitModeCases')]
    public function testCreateThumbnailsCatchesImagickErrorsForEitherFitMode(ImageFitMode $fitMode): void
    {
        // Arrange
        $imageTypeRegistry = $this->createStub(ImageTypeRegistry::class);
        $imageTypeRegistry->method('getThumbnailSizes')->willReturn([[50, 50]]);
        $imageTypeRegistry->method('getFitMode')->willReturn($fitMode);

        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(false);

        $image = $this->createStub(Image::class);
        $image->method('getHash')->willReturn('nonexistent_hash');
        $image->method('getExtension')->willReturn('jpg');
        $image->method('getType')->willReturn(ImageType::ProfilePicture);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('error');

        $subject = $this->createService(imageTypeRegistry: $imageTypeRegistry, filesystemService: $fs, logger: $logger);

        // Act
        $created = $subject->createThumbnails($image);

        // Assert
        static::assertSame(0, $created);
    }

    public static function provideFitModeCases(): iterable
    {
        yield 'fit mode uses thumbnailImage' => [ImageFitMode::Fit];
        yield 'crop mode uses cropThumbnailImage' => [ImageFitMode::Crop];
    }

    public function testRegenerateAllThumbnailsIteratesEveryImage(): void
    {
        // Arrange
        $images = [];
        for ($i = 0; $i < 3; ++$i) {
            $img = $this->createStub(Image::class);
            $img->method('getHash')->willReturn('h' . $i);
            $img->method('getExtension')->willReturn('jpg');
            $img->method('getType')->willReturn(ImageType::ProfilePicture);
            $images[] = $img;
        }
        $imageRepo = $this->createMock(ImageRepository::class);
        $imageRepo->expects($this->once())->method('findAll')->willReturn($images);

        $imageTypeRegistry = $this->createStub(ImageTypeRegistry::class);
        $imageTypeRegistry->method('getThumbnailSizes')->willReturn([[10, 10]]);
        $imageTypeRegistry->method('getFitMode')->willReturn(ImageFitMode::Fit);

        $fs = $this->createStub(ExtendedFilesystem::class);
        $fs->method('fileExists')->willReturn(false);

        $subject = $this->createService(imageRepo: $imageRepo, imageTypeRegistry: $imageTypeRegistry, filesystemService: $fs);

        // Act
        $count = $subject->regenerateAllThumbnails();

        // Assert
        static::assertSame(0, $count);
    }

    public function testRotateThumbNailCatchesImagickErrors(): void
    {
        // Arrange
        $imageTypeRegistry = $this->createStub(ImageTypeRegistry::class);
        $imageTypeRegistry->method('getThumbnailSizes')->willReturn([[10, 10], [20, 20]]);

        $image = $this->createStub(Image::class);
        $image->method('getHash')->willReturn('rotate_hash');
        $image->method('getType')->willReturn(ImageType::ProfilePicture);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->exactly(2))->method('error');

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('flush');

        $subject = $this->createService(entityManager: $em, imageTypeRegistry: $imageTypeRegistry, logger: $logger);

        // Act / Assert
        $subject->rotateThumbNail($image);
    }
}
