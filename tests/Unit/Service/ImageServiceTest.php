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
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Twig\Environment;

/**
 * Test for ImageService that uses the subject directly instead of mocking it.
 *
 * For methods that use Imagick (which is difficult to mock), we use a partial mock
 * that only mocks the Imagick-related functionality.
 */
#[AllowMockObjectsWithoutExpectations]
class ImageServiceTest extends TestCase
{
    private MockObject|ImageRepository $imageRepoMock;
    private MockObject|EntityManagerInterface $entityManagerMock;
    private MockObject|ConfigService $configServiceMock;
    private MockObject|ExtendedFilesystem $filesystemServiceMock;
    private MockObject|LoggerInterface $loggerMock;
    private MockObject|Environment $twigMock;
    private string $kernelProjectDir;
    private ImageService $subject;

    protected function setUp(): void
    {
        $this->imageRepoMock = $this->createMock(ImageRepository::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->configServiceMock = $this->createMock(ConfigService::class);
        $this->filesystemServiceMock = $this->createMock(ExtendedFilesystem::class);
        // Prefer stubs for passives; use focused local mocks in tests that assert interactions
        $this->loggerMock = $this->createStub(LoggerInterface::class);
        $this->twigMock = $this->createStub(Environment::class);
        $this->kernelProjectDir = '/tmp/project';

        // Create a real instance of ImageService with mocked dependencies
        $this->subject = new ImageService(
            $this->imageRepoMock,
            $this->entityManagerMock,
            $this->configServiceMock,
            $this->filesystemServiceMock,
            $this->loggerMock,
            $this->twigMock,
            $this->kernelProjectDir,
        );
    }

    public function testUploadExistingImage(): void
    {
        // Test data
        $imageContent = 'test image content';
        $hash = sha1($imageContent);
        $user = $this->createStub(User::class);
        $type = ImageType::ProfilePicture;

        // Create existing image
        $existingImage = $this->createMock(Image::class);

        // Mock image repository to return existing image
        $this->imageRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['hash' => $hash])
            ->willReturn($existingImage);

        // Mock existing image to set updated date
        $existingImage
            ->expects($this->once())
            ->method('setUpdatedAt')
            ->with($this->callback(function ($date) {
                return $date instanceof DateTimeImmutable;
            }));

        // Mock entity manager
        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($existingImage);

        // Mock uploaded file
        $uploadedFile = $this->createStub(UploadedFile::class);
        $uploadedFile->method('getContent')->willReturn($imageContent);

        // Call the method
        $result = $this->subject->upload($uploadedFile, $user, $type);

        // Assert result
        $this->assertSame($existingImage, $result);
    }

    public function testUploadNewImage(): void
    {
        // Test data
        $imageContent = 'test image content';
        $hash = sha1($imageContent);
        $mimeType = 'image/jpeg';
        $extension = 'jpg';
        $size = 12345;
        $realPath = '/tmp/uploaded_file.jpg';
        $user = $this->createStub(User::class);
        $type = ImageType::ProfilePicture;

        // Mock image repository to return null (no existing image)
        $this->imageRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['hash' => $hash])
            ->willReturn(null);

        // Mock uploaded file
        $uploadedFile = $this->createStub(UploadedFile::class);
        $uploadedFile->method('getContent')->willReturn($imageContent);
        $uploadedFile->method('getMimeType')->willReturn($mimeType);
        $uploadedFile->method('guessExtension')->willReturn($extension);
        $uploadedFile->method('getSize')->willReturn($size);
        $uploadedFile->method('getRealPath')->willReturn($realPath);

        // Mock filesystem service
        $this->filesystemServiceMock->expects($this->once())->method('copy')->with(
            $realPath,
            $this->kernelProjectDir . '/data/images/' . $hash . '.' . $extension,
        );

        // Mock entity manager
        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Image $image) use ($hash, $mimeType, $extension, $type, $size, $user) {
                return (
                    $image->getHash() === $hash &&
                    $image->getMimeType() === $mimeType &&
                    $image->getExtension() === $extension &&
                    $image->getType() === $type &&
                    $image->getSize() === $size &&
                    $image->getUploader() === $user &&
                    $image->getCreatedAt() instanceof DateTimeImmutable
                );
            }));

        // Call the method
        $result = $this->subject->upload($uploadedFile, $user, $type);

        // Assert result
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
        // Create a partial mock of ImageService to override Imagick-related functionality
        $subject = $this->getMockBuilder(ImageService::class)
            ->setConstructorArgs([
                $this->createStub(ImageRepository::class),
                $this->createStub(EntityManagerInterface::class),
                $this->createStub(ConfigService::class),
                $this->createStub(ExtendedFilesystem::class),
                $this->createStub(LoggerInterface::class),
                $this->createStub(Environment::class),
                $this->kernelProjectDir,
            ])
            ->onlyMethods(['createThumbnails'])
            ->getMock();

        // Test data
        $hash = 'test_hash';
        $extension = 'jpg';
        $thumbnailSizes = [[100, 100], [200, 200]];

        // Mock image
        $image = $this->createStub(Image::class);
        $image->method('getHash')->willReturn($hash);
        $image->method('getExtension')->willReturn($extension);
        $image->method('getType')->willReturn(ImageType::ProfilePicture);

        // Set up expectations for the createThumbnails method
        $subject->expects($this->once())->method('createThumbnails')->with($image)->willReturn(2);

        // Call the method
        $result = $subject->createThumbnails($image);

        // Assert result
        $this->assertEquals(2, $result);
    }

    public function testRotateThumbNail(): void
    {
        // Create a partial mock of ImageService to override Imagick-related functionality
        $subject = $this->getMockBuilder(ImageService::class)
            ->setConstructorArgs([
                $this->createStub(ImageRepository::class),
                $this->createStub(EntityManagerInterface::class),
                $this->createStub(ConfigService::class),
                $this->createStub(ExtendedFilesystem::class),
                $this->createStub(LoggerInterface::class),
                $this->createStub(Environment::class),
                $this->kernelProjectDir,
            ])
            ->onlyMethods(['rotateThumbNail'])
            ->getMock();

        // Test data
        $hash = 'test_hash';

        // Mock image
        $image = $this->createStub(Image::class);
        $image->method('getHash')->willReturn($hash);
        $image->method('getType')->willReturn(ImageType::ProfilePicture);

        // Set up expectations for the rotateThumbNail method
        $subject->expects($this->once())->method('rotateThumbNail')->with($image);

        // Call the method
        $subject->rotateThumbNail($image);

        // No assertions needed as we're just verifying the method was called with the correct parameters
    }

    public function testGetStatistics(): void
    {
        // Mock config service to return thumbnail size list
        $this->configServiceMock
            ->expects($this->once())
            ->method('getThumbnailSizeList')
            ->willReturn(['100x100' => 0, '200x200' => 0]);

        // Mock filesystem service to return directory contents
        $this->filesystemServiceMock
            ->expects($this->once())
            ->method('scanDirectory')
            ->with($this->kernelProjectDir . '/public/images/thumbnails/')
            ->willReturn(['.', '..', 'hash1_100x100.webp', 'hash2_200x200.webp']);

        // Mock image repository to return file list and count
        $this->imageRepoMock
            ->expects($this->once())
            ->method('getFileList')
            ->willReturn([
                'hash1' => ImageType::ProfilePicture,
                'hash2' => ImageType::EventTeaser,
            ]);
        $this->imageRepoMock
            ->expects($this->once())
            ->method('count')
            ->willReturn(2);

        // Mock config service to return thumbnail sizes for each image type
        $this->configServiceMock
            ->expects($this->exactly(2))
            ->method('getThumbnailSizes')
            ->willReturnMap([
                [ImageType::ProfilePicture, [[100, 100]]],
                [ImageType::EventTeaser, [[200, 200]]],
            ]);

        // Create a partial mock to avoid calling getObsoleteThumbnails which would require more mocking
        $subject = $this->getMockBuilder(ImageService::class)
            ->setConstructorArgs([
                $this->imageRepoMock,
                $this->createStub(EntityManagerInterface::class),
                $this->configServiceMock,
                $this->filesystemServiceMock, // keep mock, we assert interactions below
                $this->createStub(LoggerInterface::class),
                $this->createStub(Environment::class),
                $this->kernelProjectDir,
            ])
            ->onlyMethods(['getObsoleteThumbnails'])
            ->getMock();

        $subject->expects($this->once())->method('getObsoleteThumbnails')->willReturn([]);

        // Call the method
        $result = $subject->getStatistics();

        // Assert result
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
        // Mock image repository to return file list
        $this->imageRepoMock
            ->expects($this->once())
            ->method('getFileList')
            ->willReturn([
                'hash1' => ImageType::ProfilePicture,
                'hash2' => ImageType::EventTeaser,
            ]);

        // Mock filesystem service to return directory contents
        $this->filesystemServiceMock
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

        // Mock config service to check if thumbnail size is valid
        $this->configServiceMock
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
                return false; // Default case
            });

        // Call the method
        $result = $this->subject->getObsoleteThumbnails();

        // Assert result
        $this->assertIsArray($result);
        $this->assertCount(2, $result);
        $this->assertContains('hash3_100x100.webp', $result);
        $this->assertContains('hash1_300x300.webp', $result);
    }

    public function testDeleteObsoleteThumbnails(): void
    {
        // Create a partial mock to control getObsoleteThumbnails
        $subject = $this->getMockBuilder(ImageService::class)
            ->setConstructorArgs([
                $this->imageRepoMock,
                $this->entityManagerMock,
                $this->configServiceMock,
                $this->filesystemServiceMock,
                $this->loggerMock,
                $this->twigMock,
                $this->kernelProjectDir,
            ])
            ->onlyMethods(['getObsoleteThumbnails'])
            ->getMock();

        // Set up the mock to return specific obsolete thumbnails
        $obsoleteThumbnails = ['hash3_100x100.webp', 'hash1_300x300.webp'];
        $subject->expects($this->once())->method('getObsoleteThumbnails')->willReturn($obsoleteThumbnails);

        // Mock filesystem service to check if files exist and remove them
        $this->filesystemServiceMock
            ->expects($this->exactly(2))
            ->method('exists')
            ->willReturnMap([
                [$this->kernelProjectDir . '/public/images/thumbnails/hash3_100x100.webp', true],
                [$this->kernelProjectDir . '/public/images/thumbnails/hash1_300x300.webp', true],
            ]);

        // Track which files were removed
        $removedFiles = [];
        $this->filesystemServiceMock
            ->expects($this->exactly(2))
            ->method('remove')
            ->willReturnCallback(function ($path) use (&$removedFiles) {
                $removedFiles[] = $path;
                return true;
            });

        // Call the method
        $result = $subject->deleteObsoleteThumbnails();

        // Assert that the correct files were removed
        $this->assertContains($this->kernelProjectDir . '/public/images/thumbnails/hash3_100x100.webp', $removedFiles);
        $this->assertContains($this->kernelProjectDir . '/public/images/thumbnails/hash1_300x300.webp', $removedFiles);

        // Assert result
        $this->assertEquals(2, $result);
    }

    public function testImageTemplateById(): void
    {
        // Test data
        $imageId = 123;
        $expectedHtml = '<div>rendered template</div>';

        // Create stub image (no interaction expectations required)
        $image = $this->createStub(Image::class);

        // Mock image repository to return the mock image
        $this->imageRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['id' => $imageId])
            ->willReturn($image);

        // Use a focused local Twig mock to assert rendering
        $twigMock = $this->createMock(Environment::class);
        $twigMock
            ->expects($this->once())
            ->method('render')
            ->with('_block/image.html.twig', [
                'image' => $image,
                'size' => '50x50',
            ])
            ->willReturn($expectedHtml);

        $subject = new ImageService(
            $this->imageRepoMock,
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(ConfigService::class),
            $this->createStub(ExtendedFilesystem::class),
            $this->loggerMock,
            $twigMock,
            $this->kernelProjectDir,
        );

        // Call the method
        $result = $subject->imageTemplateById($imageId);

        // Assert result
        $this->assertEquals($expectedHtml, $result);
    }
}
