<?php declare(strict_types=1);

namespace Tests\Unit\Service\ImageLocations;

use App\Enum\CmsBlock\CmsBlockType;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Service\Media\ImageLocations\CmsBlockLocationProvider;
use App\Service\Media\ImageLocations\CmsCardImageLocationProvider;
use App\Service\Media\ImageLocations\CmsGalleryLocationProvider;
use App\Service\Media\ImageLocations\EventTeaserLocationProvider;
use App\Service\Media\ImageLocations\EventUploadLocationProvider;
use App\Service\Media\ImageLocations\LanguageTileLocationProvider;
use App\Service\Media\ImageLocations\ProfilePictureLocationProvider;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;

class ImageLocationProvidersTest extends TestCase
{
    private function makeRepo(): ImageLocationRepository
    {
        return $this->createStub(ImageLocationRepository::class);
    }

    // =========================================================================
    // ProfilePictureLocationProvider
    // =========================================================================

    public function testProfilePictureGetType(): void
    {
        $provider = new ProfilePictureLocationProvider($this->makeRepo(), $this->createStub(Connection::class));
        static::assertSame(ImageType::ProfilePicture, $provider->getType());
    }

    public function testProfilePictureGetEditLink(): void
    {
        $provider = new ProfilePictureLocationProvider($this->makeRepo(), $this->createStub(Connection::class));
        static::assertSame(['route' => 'app_admin_member_edit', 'params' => ['id' => 5]], $provider->getEditLink(5));
    }

    public function testProfilePictureDiscoverImageIds(): void
    {
        // Arrange
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([
            ['image_id' => '10', 'location_id' => '1'],
            ['image_id' => '20', 'location_id' => '2'],
        ]);

        // Act
        $result = (new ProfilePictureLocationProvider($this->makeRepo(), $conn))->discoverImageIds();

        // Assert
        static::assertSame([
            ['imageId' => 10, 'locationId' => 1],
            ['imageId' => 20, 'locationId' => 2],
        ], $result);
    }

    // =========================================================================
    // EventTeaserLocationProvider
    // =========================================================================

    public function testEventTeaserGetType(): void
    {
        $provider = new EventTeaserLocationProvider($this->makeRepo(), $this->createStub(Connection::class));
        static::assertSame(ImageType::EventTeaser, $provider->getType());
    }

    public function testEventTeaserGetEditLink(): void
    {
        $provider = new EventTeaserLocationProvider($this->makeRepo(), $this->createStub(Connection::class));
        static::assertSame(['route' => 'app_admin_event_edit', 'params' => ['id' => 7]], $provider->getEditLink(7));
    }

    public function testEventTeaserDiscoverImageIds(): void
    {
        // Arrange
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([
            ['image_id' => '3', 'location_id' => '99'],
        ]);

        // Act
        $result = (new EventTeaserLocationProvider($this->makeRepo(), $conn))->discoverImageIds();

        // Assert
        static::assertSame([['imageId' => 3, 'locationId' => 99]], $result);
    }

    // =========================================================================
    // EventUploadLocationProvider
    // =========================================================================

    public function testEventUploadGetType(): void
    {
        $provider = new EventUploadLocationProvider($this->makeRepo(), $this->createStub(Connection::class));
        static::assertSame(ImageType::EventUpload, $provider->getType());
    }

    public function testEventUploadGetEditLink(): void
    {
        $provider = new EventUploadLocationProvider($this->makeRepo(), $this->createStub(Connection::class));
        static::assertSame(['route' => 'app_admin_event_edit', 'params' => ['id' => 12]], $provider->getEditLink(12));
    }

    public function testEventUploadDiscoverImageIds(): void
    {
        // Arrange
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([
            ['image_id' => '55', 'location_id' => '8'],
        ]);

        // Act
        $result = (new EventUploadLocationProvider($this->makeRepo(), $conn))->discoverImageIds();

        // Assert
        static::assertSame([['imageId' => 55, 'locationId' => 8]], $result);
    }

    // =========================================================================
    // CmsBlockLocationProvider
    // =========================================================================

    public function testCmsBlockGetType(): void
    {
        $provider = new CmsBlockLocationProvider($this->makeRepo(), $this->createStub(Connection::class));
        static::assertSame(ImageType::CmsBlock, $provider->getType());
    }

    public function testCmsBlockGetEditLink(): void
    {
        $provider = new CmsBlockLocationProvider($this->makeRepo(), $this->createStub(Connection::class));
        static::assertSame(['route' => 'app_admin_cms_block_edit', 'params' => ['blockId' => 3]], $provider->getEditLink(3));
    }

    public function testCmsBlockDiscoverImageIds(): void
    {
        // Arrange
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([
            ['image_id' => '4', 'location_id' => '11'],
        ]);

        // Act
        $result = (new CmsBlockLocationProvider($this->makeRepo(), $conn))->discoverImageIds();

        // Assert
        static::assertSame([['imageId' => 4, 'locationId' => 11]], $result);
    }

    // =========================================================================
    // LanguageTileLocationProvider
    // =========================================================================

    public function testLanguageTileGetType(): void
    {
        $provider = new LanguageTileLocationProvider($this->makeRepo(), $this->createStub(Connection::class));
        static::assertSame(ImageType::LanguageTile, $provider->getType());
    }

    public function testLanguageTileGetEditLink(): void
    {
        $provider = new LanguageTileLocationProvider($this->makeRepo(), $this->createStub(Connection::class));
        static::assertSame(['route' => 'app_admin_language_edit', 'params' => ['id' => 9]], $provider->getEditLink(9));
    }

    public function testLanguageTileDiscoverImageIds(): void
    {
        // Arrange
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([
            ['image_id' => '6', 'location_id' => '2'],
        ]);

        // Act
        $result = (new LanguageTileLocationProvider($this->makeRepo(), $conn))->discoverImageIds();

        // Assert
        static::assertSame([['imageId' => 6, 'locationId' => 2]], $result);
    }

    // =========================================================================
    // CmsGalleryLocationProvider
    // =========================================================================

    public function testCmsGalleryGetType(): void
    {
        $provider = new CmsGalleryLocationProvider($this->makeRepo(), $this->createStub(Connection::class));
        static::assertSame(ImageType::CmsGallery, $provider->getType());
    }

    public function testCmsGalleryGetEditLink(): void
    {
        $provider = new CmsGalleryLocationProvider($this->makeRepo(), $this->createStub(Connection::class));
        static::assertSame(['route' => 'app_admin_cms_block_edit', 'params' => ['blockId' => 4]], $provider->getEditLink(4));
    }

    public function testCmsGalleryDiscoverImageIdsExtractsFromJson(): void
    {
        // Arrange
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([
            ['id' => '7', 'json' => json_encode(['images' => [['id' => 10], ['id' => 11]]])],
            ['id' => '8', 'json' => json_encode(['images' => []])],
        ]);

        // Act
        $result = (new CmsGalleryLocationProvider($this->makeRepo(), $conn))->discoverImageIds();

        // Assert
        static::assertSame([
            ['imageId' => 10, 'locationId' => 7],
            ['imageId' => 11, 'locationId' => 7],
        ], $result);
    }

    public function testCmsGalleryDiscoverImageIdsSkipsItemsWithoutId(): void
    {
        // Arrange
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([
            ['id' => '5', 'json' => json_encode(['images' => [['caption' => 'no id here']]])],
        ]);

        // Act
        $result = (new CmsGalleryLocationProvider($this->makeRepo(), $conn))->discoverImageIds();

        // Assert
        static::assertSame([], $result);
    }

    // =========================================================================
    // CmsCardImageLocationProvider
    // =========================================================================

    public function testCmsCardImageGetType(): void
    {
        $provider = new CmsCardImageLocationProvider($this->makeRepo(), $this->createStub(Connection::class));
        static::assertSame(ImageType::CmsCardImage, $provider->getType());
    }

    public function testCmsCardImageGetEditLink(): void
    {
        $provider = new CmsCardImageLocationProvider($this->makeRepo(), $this->createStub(Connection::class));
        static::assertSame(['route' => 'app_admin_cms_block_edit', 'params' => ['blockId' => 6]], $provider->getEditLink(6));
    }

    public function testCmsCardImageDiscoverImageIdsExtractsFromCards(): void
    {
        // Arrange
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([
            ['id' => '3', 'json' => json_encode(['cards' => [
                ['image' => ['id' => 20]],
                ['image' => ['id' => 21]],
            ]])],
        ]);

        // Act
        $result = (new CmsCardImageLocationProvider($this->makeRepo(), $conn))->discoverImageIds();

        // Assert
        static::assertSame([
            ['imageId' => 20, 'locationId' => 3],
            ['imageId' => 21, 'locationId' => 3],
        ], $result);
    }

    public function testCmsCardImageDiscoverImageIdsSkipsCardsMissingImageId(): void
    {
        // Arrange
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([
            ['id' => '2', 'json' => json_encode(['cards' => [
                ['image' => null],
                ['image' => ['no_id' => true]],
                [],
            ]])],
        ]);

        // Act
        $result = (new CmsCardImageLocationProvider($this->makeRepo(), $conn))->discoverImageIds();

        // Assert
        static::assertSame([], $result);
    }
}
