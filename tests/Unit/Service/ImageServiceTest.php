<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Image;
use App\Entity\ImageType;
use App\Entity\User;
use App\ExtendedFilesystem;
use App\Repository\ImageRepository;
use App\Service\ConfigService;
use App\Service\ImageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageServiceTest extends TestCase
{
    private string $kernelProjectDir = '/tmp/project';

    private function createService(
        ?ImageRepository $imageRepo = null,
        ?EntityManagerInterface $entityManager = null,
        ?ConfigService $configService = null,
        ?ExtendedFilesystem $filesystemService = null,
        ?LoggerInterface $logger = null,
    ): ImageService {
        return new ImageService(
            $imageRepo ?? $this->createStub(ImageRepository::class),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $configService ?? $this->createStub(ConfigService::class),
            $filesystemService ?? $this->createStub(ExtendedFilesystem::class),
            $logger ?? $this->createStub(LoggerInterface::class),
            $this->kernelProjectDir,
        );
    }

    public function testUploadExistingImage(): void
    {
        // Arrange: test data
        $imageContent = 'test image content';
        $hash = sha1($imageContent);
        $user = $this->createStub(User::class);
        $type = ImageType::ProfilePicture;

        // Arrange: mock existing image to verify setUpdatedAt is called
        $existingImage = $this->createMock(Image::class);
        $existingImage
            ->expects($this->once())
            ->method('setUpdatedAt')
            ->with($this->callback(function ($date) {
                return $date instanceof DateTimeImmutable;
            }));

        // Arrange: mock image repository to return existing image
        $imageRepoMock = $this->createMock(ImageRepository::class);
        $imageRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['hash' => $hash])
            ->willReturn($existingImage);

        // Arrange: mock entity manager to verify persist
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($existingImage);

        // Arrange: stub uploaded file
        $uploadedFile = $this->createStub(UploadedFile::class);
        $uploadedFile->method('getContent')->willReturn($imageContent);

        $subject = $this->createService(
            imageRepo: $imageRepoMock,
            entityManager: $entityManagerMock,
        );

        // Act: upload existing image
        $result = $subject->upload($uploadedFile, $user, $type);

        // Assert: returns existing image
        $this->assertSame($existingImage, $result);
    }

    public function testUploadNewImage(): void
    {
        // Arrange: test data
        $imageContent = 'test image content';
        $hash = sha1($imageContent);
        $mimeType = 'image/jpeg';
        $extension = 'jpg';
        $size = 12345;
        $realPath = '/tmp/uploaded_file.jpg';
        $user = $this->createStub(User::class);
        $type = ImageType::ProfilePicture;

        // Arrange: mock image repository to return null (no existing image)
        $imageRepoMock = $this->createMock(ImageRepository::class);
        $imageRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['hash' => $hash])
            ->willReturn(null);

        // Arrange: stub uploaded file
        $uploadedFile = $this->createStub(UploadedFile::class);
        $uploadedFile->method('getContent')->willReturn($imageContent);
        $uploadedFile->method('getMimeType')->willReturn($mimeType);
        $uploadedFile->method('guessExtension')->willReturn($extension);
        $uploadedFile->method('getSize')->willReturn($size);
        $uploadedFile->method('getRealPath')->willReturn($realPath);

        // Arrange: mock filesystem service to verify copy
        $filesystemMock = $this->createMock(ExtendedFilesystem::class);
        $filesystemMock->expects($this->once())->method('copy')->with(
            $realPath,
            $this->kernelProjectDir . '/data/images/' . $hash . '.' . $extension,
        );

        // Arrange: mock entity manager to verify persist with correct image data
        $entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Image $image) use ($hash, $mimeType, $extension, $type, $size, $user) {
                return
                    $image->getHash() === $hash
                    && $image->getMimeType() === $mimeType
                    && $image->getExtension() === $extension
                    && $image->getType() === $type
                    && $image->getSize() === $size
                    && $image->getUploader() === $user
                    && $image->getCreatedAt() instanceof DateTimeImmutable
                ;
            }));

        $subject = $this->createService(
            imageRepo: $imageRepoMock,
            entityManager: $entityManagerMock,
            filesystemService: $filesystemMock,
        );

        // Act: upload new image
        $result = $subject->upload($uploadedFile, $user, $type);

        // Assert: returns new image with correct properties
        $this->assertInstanceOf(Image::class, $result);
        $this->assertEquals($hash, $result->getHash());
        $this->assertEquals($mimeType, $result->getMimeType());
        $this->assertEquals($extension, $result->getExtension());
        $this->assertEquals($type, $result->getType());
        $this->assertEquals($size, $result->getSize());
        $this->assertEquals($user, $result->getUploader());
    }

    public function testCreateThumbnails(): void
    {
        // Arrange: create a partial mock to override Imagick-related functionality
        $subject = $this->getMockBuilder(ImageService::class)
            ->setConstructorArgs([
                $this->createStub(ImageRepository::class),
                $this->createStub(EntityManagerInterface::class),
                $this->createStub(ConfigService::class),
                $this->createStub(ExtendedFilesystem::class),
                $this->createStub(LoggerInterface::class),
                $this->kernelProjectDir,
            ])
            ->onlyMethods(['createThumbnails'])
            ->getMock();

        // Arrange: stub image
        $image = $this->createStub(Image::class);
        $image->method('getHash')->willReturn('test_hash');
        $image->method('getExtension')->willReturn('jpg');
        $image->method('getType')->willReturn(ImageType::ProfilePicture);

        // Arrange: set up expectations for the createThumbnails method
        $subject->expects($this->once())->method('createThumbnails')->with($image)->willReturn(2);

        // Act: create thumbnails
        $result = $subject->createThumbnails($image);

        // Assert: returns expected thumbnail count
        $this->assertEquals(2, $result);
    }

    public function testRotateThumbNail(): void
    {
        // Arrange: create a partial mock to override Imagick-related functionality
        $subject = $this->getMockBuilder(ImageService::class)
            ->setConstructorArgs([
                $this->createStub(ImageRepository::class),
                $this->createStub(EntityManagerInterface::class),
                $this->createStub(ConfigService::class),
                $this->createStub(ExtendedFilesystem::class),
                $this->createStub(LoggerInterface::class),
                $this->kernelProjectDir,
            ])
            ->onlyMethods(['rotateThumbNail'])
            ->getMock();

        // Arrange: stub image
        $image = $this->createStub(Image::class);
        $image->method('getHash')->willReturn('test_hash');
        $image->method('getType')->willReturn(ImageType::ProfilePicture);

        // Arrange: set up expectations for the rotateThumbNail method
        $subject->expects($this->once())->method('rotateThumbNail')->with($image);

        // Act: rotate thumbnail
        $subject->rotateThumbNail($image);
    }

    public function testGetStatistics(): void
    {
        // Arrange: mock config service to return thumbnail size list
        $configServiceMock = $this->createMock(ConfigService::class);
        $configServiceMock
            ->expects($this->once())
            ->method('getThumbnailSizeList')
            ->willReturn(['100x100' => 0, '200x200' => 0]);
        $configServiceMock
            ->expects($this->exactly(2))
            ->method('getThumbnailSizes')
            ->willReturnMap([
                [ImageType::ProfilePicture, [[100, 100]]],
                [ImageType::EventTeaser, [[200, 200]]],
            ]);

        // Arrange: mock filesystem service to return directory contents
        $filesystemMock = $this->createMock(ExtendedFilesystem::class);
        $filesystemMock
            ->expects($this->once())
            ->method('scanDirectory')
            ->with($this->kernelProjectDir . '/public/images/thumbnails/')
            ->willReturn(['.', '..', 'hash1_100x100.webp', 'hash2_200x200.webp']);

        // Arrange: mock image repository to return file list and count
        $imageRepoMock = $this->createMock(ImageRepository::class);
        $imageRepoMock
            ->expects($this->once())
            ->method('getFileList')
            ->willReturn([
                'hash1' => ImageType::ProfilePicture,
                'hash2' => ImageType::EventTeaser,
            ]);
        $imageRepoMock
            ->expects($this->once())
            ->method('count')
            ->willReturn(2);

        // Arrange: create partial mock to avoid calling getObsoleteThumbnails
        $subject = $this->getMockBuilder(ImageService::class)
            ->setConstructorArgs([
                $imageRepoMock,
                $this->createStub(EntityManagerInterface::class),
                $configServiceMock,
                $filesystemMock,
                $this->createStub(LoggerInterface::class),
                $this->kernelProjectDir,
            ])
            ->onlyMethods(['getObsoleteThumbnails'])
            ->getMock();

        $subject->expects($this->once())->method('getObsoleteThumbnails')->willReturn([]);

        // Act: get statistics
        $result = $subject->getStatistics();

        // Assert: returns expected structure with correct values
        $this->assertIsArray($result);
        $this->assertArrayHasKey('imageCount', $result);
        $this->assertArrayHasKey('imageTypeList', $result);
        $this->assertArrayHasKey('thumbnailSizeList', $result);
        $this->assertArrayHasKey('thumbnailCount', $result);
        $this->assertArrayHasKey('thumbnailObsoleteCount', $result);
        $this->assertArrayHasKey('thumbnailMissingCount', $result);
        $this->assertEquals(2, $result['imageCount']);
        $this->assertEquals(['ProfilePicture' => 1, 'EventTeaser' => 1], $result['imageTypeList']);
        $this->assertEquals(['100x100' => 1, '200x200' => 1], $result['thumbnailSizeList']);
        $this->assertEquals(2, $result['thumbnailCount']);
        $this->assertEquals(0, $result['thumbnailObsoleteCount']);
        $this->assertEquals(0, $result['thumbnailMissingCount']);
    }

    public function testGetObsoleteThumbnails(): void
    {
        // Arrange: mock image repository to return file list
        $imageRepoMock = $this->createMock(ImageRepository::class);
        $imageRepoMock
            ->expects($this->once())
            ->method('getFileList')
            ->willReturn([
                'hash1' => ImageType::ProfilePicture,
                'hash2' => ImageType::EventTeaser,
            ]);

        // Arrange: mock filesystem service to return directory contents
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

        // Arrange: mock config service to check if thumbnail size is valid
        // Note: isValidThumbnailSize is only called for hashes that exist in the file list
        // hash3 is not in the list, so we only get 3 calls (hash1_100x100, hash2_200x200, hash1_300x300)
        $configServiceMock = $this->createMock(ConfigService::class);
        $configServiceMock
            ->expects($this->exactly(3))
            ->method('isValidThumbnailSize')
            ->willReturnCallback(function (ImageType $type, int $width, int $height) {
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

        $subject = $this->createService(
            imageRepo: $imageRepoMock,
            configService: $configServiceMock,
            filesystemService: $filesystemMock,
        );

        // Act: get obsolete thumbnails
        $result = $subject->getObsoleteThumbnails();

        // Assert: returns only obsolete thumbnails
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContains('hash3_100x100.webp', $result);
        $this->assertContains('hash1_300x300.webp', $result);
    }

    public function testDeleteObsoleteThumbnails(): void
    {
        // Arrange: create partial mock to control getObsoleteThumbnails
        $filesystemMock = $this->createMock(ExtendedFilesystem::class);

        $subject = $this->getMockBuilder(ImageService::class)
            ->setConstructorArgs([
                $this->createStub(ImageRepository::class),
                $this->createStub(EntityManagerInterface::class),
                $this->createStub(ConfigService::class),
                $filesystemMock,
                $this->createStub(LoggerInterface::class),
                $this->kernelProjectDir,
            ])
            ->onlyMethods(['getObsoleteThumbnails'])
            ->getMock();

        $obsoleteThumbnails = ['hash3_100x100.webp', 'hash1_300x300.webp'];
        $subject->expects($this->once())->method('getObsoleteThumbnails')->willReturn($obsoleteThumbnails);

        // Arrange: mock filesystem service to check and remove files
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
            ->willReturnCallback(function ($path) use (&$removedFiles) {
                $removedFiles[] = $path;

                return true;
            });

        // Act: delete obsolete thumbnails
        $result = $subject->deleteObsoleteThumbnails();

        // Assert: correct files were removed
        $this->assertContains($this->kernelProjectDir . '/public/images/thumbnails/hash3_100x100.webp', $removedFiles);
        $this->assertContains($this->kernelProjectDir . '/public/images/thumbnails/hash1_300x300.webp', $removedFiles);
        $this->assertEquals(2, $result);
    }
}
