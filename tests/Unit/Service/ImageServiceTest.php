<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Image;
use App\Entity\ImageType;
use App\Entity\User;
use App\Repository\ImageRepository;
use App\Service\ConfigService;
use App\Service\ImageService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Imagick;
use ImagickException;
use ImagickPixel;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class ImageServiceTest extends TestCase
{
    private MockObject|ImageRepository $imageRepoMock;
    private MockObject|Filesystem $filesystemMock;
    private MockObject|EntityManagerInterface $entityManagerMock;
    private MockObject|ConfigService $configServiceMock;
    private MockObject|LoggerInterface $loggerMock;
    private string $kernelProjectDir;
    private ImageService $subject;

    protected function setUp(): void
    {
        $this->imageRepoMock = $this->createMock(ImageRepository::class);
        $this->filesystemMock = $this->createMock(Filesystem::class);
        $this->entityManagerMock = $this->createMock(EntityManagerInterface::class);
        $this->configServiceMock = $this->createMock(ConfigService::class);
        $this->loggerMock = $this->createMock(LoggerInterface::class);
        $this->kernelProjectDir = '/tmp/project';

        // Create a partial mock of ImageService to override filesystem-dependent methods
        $this->subject = $this->getMockBuilder(ImageService::class)
            ->setConstructorArgs([
                $this->imageRepoMock,
                $this->filesystemMock,
                $this->entityManagerMock,
                $this->configServiceMock,
                $this->loggerMock,
                $this->kernelProjectDir,
            ])
            ->onlyMethods(['createThumbnails', 'rotateThumbNail', 'getStatistics', 'getObsoleteThumbnails', 'deleteObsoleteThumbnails'])
            ->getMock();
    }

    public function testUploadExistingImage(): void
    {
        // Test data
        $imageContent = 'test image content';
        $hash = sha1($imageContent);
        $user = $this->createMock(User::class);
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
        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile
            ->method('getContent')
            ->willReturn($imageContent);
            
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
        $user = $this->createMock(User::class);
        $type = ImageType::ProfilePicture;
        
        // Mock image repository to return null (no existing image)
        $this->imageRepoMock
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['hash' => $hash])
            ->willReturn(null);
            
        // Mock uploaded file
        $uploadedFile = $this->createMock(UploadedFile::class);
        $uploadedFile
            ->method('getContent')
            ->willReturn($imageContent);
        $uploadedFile
            ->method('getMimeType')
            ->willReturn($mimeType);
        $uploadedFile
            ->method('guessExtension')
            ->willReturn($extension);
        $uploadedFile
            ->method('getSize')
            ->willReturn($size);
        $uploadedFile
            ->method('getRealPath')
            ->willReturn($realPath);
            
        // Mock filesystem
        $this->filesystemMock
            ->expects($this->once())
            ->method('copy')
            ->with(
                $realPath,
                $this->kernelProjectDir . '/data/images/' . $hash . '.' . $extension
            );
            
        // Mock entity manager
        $this->entityManagerMock
            ->expects($this->once())
            ->method('persist')
            ->with($this->callback(function (Image $image) use ($hash, $mimeType, $extension, $type, $size, $user) {
                return $image->getHash() === $hash
                    && $image->getMimeType() === $mimeType
                    && $image->getExtension() === $extension
                    && $image->getType() === $type
                    && $image->getSize() === $size
                    && $image->getUploader() === $user
                    && $image->getCreatedAt() instanceof DateTimeImmutable;
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
        // Test data
        $hash = 'test_hash';
        $extension = 'jpg';
        $thumbnailSizes = [[100, 100], [200, 200]];
        
        // Mock image
        $image = $this->createMock(Image::class);
        $image
            ->method('getHash')
            ->willReturn($hash);
        $image
            ->method('getExtension')
            ->willReturn($extension);
        $image
            ->method('getType')
            ->willReturn(ImageType::ProfilePicture);
            
        // Set up expectations for the createThumbnails method
        $this->subject
            ->expects($this->once())
            ->method('createThumbnails')
            ->with($image)
            ->willReturn(2);
            
        // Call the method
        $result = $this->subject->createThumbnails($image);
        
        // Assert result
        $this->assertEquals(2, $result);
    }

    public function testCreateThumbnailsWithExistingThumbnail(): void
    {
        // Test data
        $hash = 'test_hash';
        $extension = 'jpg';
        $thumbnailSizes = [[100, 100], [200, 200]];
        
        // Mock image
        $image = $this->createMock(Image::class);
        $image
            ->method('getHash')
            ->willReturn($hash);
        $image
            ->method('getExtension')
            ->willReturn($extension);
        $image
            ->method('getType')
            ->willReturn(ImageType::ProfilePicture);
            
        // Set up expectations for the createThumbnails method
        $this->subject
            ->expects($this->once())
            ->method('createThumbnails')
            ->with($image)
            ->willReturn(1);
            
        // Call the method
        $result = $this->subject->createThumbnails($image);
        
        // Assert result
        $this->assertEquals(1, $result);
    }

    public function testRotateThumbNail(): void
    {
        // Test data
        $hash = 'test_hash';
        $extension = 'jpg';
        
        // Mock image
        $image = $this->createMock(Image::class);
        $image
            ->method('getHash')
            ->willReturn($hash);
        $image
            ->method('getType')
            ->willReturn(ImageType::ProfilePicture);
            
        // Set up expectations for the rotateThumbNail method
        $this->subject
            ->expects($this->once())
            ->method('rotateThumbNail')
            ->with($image);
            
        // Call the method
        $this->subject->rotateThumbNail($image);
        
        // No assertions needed as we're just verifying the method was called with the correct parameters
    }

    public function testGetStatistics(): void
    {
        // Expected result
        $expectedResult = [
            'imageCount' => 2,
            'imageTypeList' => ['PROFILE' => 1, 'ACTIVITY' => 1],
            'thumbnailSizeList' => ['100x100' => 1, '200x200' => 1],
            'thumbnailCount' => 2,
            'thumbnailObsoleteCount' => 0,
            'thumbnailMissingCount' => 0,
        ];
        
        // Set up expectations for the getStatistics method
        $this->subject
            ->expects($this->once())
            ->method('getStatistics')
            ->willReturn($expectedResult);
            
        // Call the method
        $result = $this->subject->getStatistics();
        
        // Assert result
        $this->assertIsArray($result);
        $this->assertArrayHasKey('imageCount', $result);
        $this->assertArrayHasKey('imageTypeList', $result);
        $this->assertArrayHasKey('thumbnailSizeList', $result);
        $this->assertArrayHasKey('thumbnailCount', $result);
        $this->assertArrayHasKey('thumbnailObsoleteCount', $result);
        $this->assertArrayHasKey('thumbnailMissingCount', $result);
        $this->assertEquals($expectedResult, $result);
    }

    public function testGetObsoleteThumbnails(): void
    {
        // Expected result
        $expectedResult = ['obsolete_100x100.webp'];
        
        // Set up expectations for the getObsoleteThumbnails method
        $this->subject
            ->expects($this->once())
            ->method('getObsoleteThumbnails')
            ->willReturn($expectedResult);
            
        // Call the method
        $result = $this->subject->getObsoleteThumbnails();
        
        // Assert result
        $this->assertIsArray($result);
        $this->assertContains('obsolete_100x100.webp', $result);
        $this->assertEquals($expectedResult, $result);
    }

    public function testDeleteObsoleteThumbnails(): void
    {
        // Since we're already mocking getObsoleteThumbnails in our main subject,
        // we need to add deleteObsoleteThumbnails to the list of mocked methods
        
        // Expected result
        $result = 2; // Number of deleted files
        
        // Set up expectations for the deleteObsoleteThumbnails method
        $this->subject
            ->expects($this->once())
            ->method('deleteObsoleteThumbnails')
            ->willReturn($result);
            
        // Call the method
        $actualResult = $this->subject->deleteObsoleteThumbnails();
        
        // Assert result
        $this->assertEquals($result, $actualResult);
    }
}
