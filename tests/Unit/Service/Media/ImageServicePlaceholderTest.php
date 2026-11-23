<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media;

use App\Entity\Image;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Repository\ImageRepository;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageService;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use App\Service\Media\MetadataStripper;
use App\Service\Media\ThumbnailSizeFormat;
use Doctrine\ORM\EntityManagerInterface;
use League\Flysystem\Filesystem;
use League\Flysystem\InMemory\InMemoryFilesystemAdapter;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use ReflectionProperty;
use Symfony\Component\Lock\LockFactory;
use Symfony\Component\Lock\Store\InMemoryStore;

class ImageServicePlaceholderTest extends TestCase
{
    private Filesystem $images;
    private Filesystem $thumbnails;

    /** @var list<array{int, string, string, string, int}> */
    private array $replaced = [];

    protected function setUp(): void
    {
        $this->images = new Filesystem(new InMemoryFilesystemAdapter());
        $this->thumbnails = new Filesystem(new InMemoryFilesystemAdapter());
        $this->replaced = [];
    }

    public function testThePlaceholderReplacesTheOriginalAndItsThumbnails(): void
    {
        // Arrange
        $this->images->write('old.jpg', 'a member photo');
        $this->thumbnails->write('old_400x300.webp', 'thumbnail');
        $this->thumbnails->write('old_800x600.webp', 'thumbnail');
        $this->thumbnails->write('other_400x300.webp', 'thumbnail');
        $subject = $this->createService();

        // Act
        $count = $subject->replaceWithPlaceholders([$this->image(7, 'old', 'jpg')]);

        // Assert
        [$id, $hash, $mimeType, $extension, $size] = $this->replaced[0];
        $content = $this->images->read($hash . '.png');
        self::assertSame(1, $count);
        self::assertSame([7, 'image/png', 'png', strlen($content)], [$id, $mimeType, $extension, $size]);
        self::assertSame(sha1($content), $hash);
        self::assertSame([800, 600], array_slice((array) getimagesizefromstring($content), 0, 2));
        self::assertFalse($this->images->fileExists('old.jpg'));
        self::assertFalse($this->thumbnails->fileExists('old_400x300.webp'));
        self::assertFalse($this->thumbnails->fileExists('old_800x600.webp'));
        self::assertTrue($this->thumbnails->fileExists('other_400x300.webp'));
    }

    public function testEveryImageGetsAPlaceholderOfItsOwn(): void
    {
        // Arrange
        $subject = $this->createService();

        // Act
        $subject->replaceWithPlaceholders([$this->image(1, 'first', 'jpg'), $this->image(2, 'second', 'png')]);

        // Assert
        self::assertNotSame($this->replaced[0][1], $this->replaced[1][1]);
    }

    private function image(int $id, string $hash, string $extension): Image
    {
        $image = new Image()
            ->setHash($hash)
            ->setExtension($extension)
            ->setType(ImageType::ProfilePicture);
        new ReflectionProperty(Image::class, 'id')->setValue($image, $id);

        return $image;
    }

    private function createService(): ImageService
    {
        $imageRepo = $this->createStub(ImageRepository::class);
        $imageRepo
            ->method('replaceFile')
            ->willReturnCallback(function (int $id, string $hash, string $mimeType, string $extension, int $size): void {
                $this->replaced[] = [$id, $hash, $mimeType, $extension, $size];
            });
        $registry = $this->createStub(ImageTypeRegistry::class);
        $registry->method('getThumbnailSizes')->willReturn([[400, 300], [800, 600]]);
        $locations = $this->createStub(ImageLocationRepository::class);
        $locations->method('findTypesPerImageId')->willReturn([]);

        return new ImageService(
            $imageRepo,
            $this->createStub(EntityManagerInterface::class),
            $registry,
            new ThumbnailSizeFormat(),
            $this->images,
            $this->thumbnails,
            $this->createStub(LoggerInterface::class),
            $this->createStub(ImageLocationService::class),
            $locations,
            new LockFactory(new InMemoryStore()),
            new MetadataStripper(),
        );
    }
}
