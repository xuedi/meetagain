<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media\ImageTypes;

use App\Entity\Cms;
use App\Entity\CmsBlock;
use App\Entity\Image;
use App\Entity\Language;
use App\Entity\User;
use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\Repository\CmsBlockRepository;
use App\Repository\EventRepository;
use App\Repository\ImageLocationRepository;
use App\Repository\LanguageRepository;
use App\Repository\UserRepository;
use App\Service\Media\ImageTypes\CmsBlockImageTypeDefinition;
use App\Service\Media\ImageTypes\CmsCardImageImageTypeDefinition;
use App\Service\Media\ImageTypes\CmsGalleryImageTypeDefinition;
use App\Service\Media\ImageTypes\EventTeaserImageTypeDefinition;
use App\Service\Media\ImageTypes\EventUploadImageTypeDefinition;
use App\Service\Media\ImageTypes\LanguageTileImageTypeDefinition;
use App\Service\Media\ImageTypes\ProfilePictureImageTypeDefinition;
use App\Service\Media\ImageTypes\SiteLogoImageTypeDefinition;
use App\Service\Media\ImageTypes\WebsiteImageImageTypeDefinition;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Tests\Unit\Stubs\EventStub;

class CoreImageTypeDefinitionsTest extends TestCase
{
    private function repo(): ImageLocationRepository
    {
        return $this->createStub(ImageLocationRepository::class);
    }

    // =========================================================================
    // ProfilePicture
    // =========================================================================

    public function testProfilePictureIdentityAndSizes(): void
    {
        $definition = new ProfilePictureImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $this->createStub(UserRepository::class));

        static::assertSame(ImageType::ProfilePicture, $definition->getType());
        static::assertSame(ImageFitMode::Crop, $definition->fitMode());
        static::assertSame([[400, 400], [350, 350], [80, 80], [100, 100], [50, 50]], $definition->thumbnailSizes());
        static::assertSame(['route' => 'app_admin_member_edit', 'params' => ['id' => 5]], $definition->getEditLink(5));
    }

    public function testProfilePictureDiscoverImageIds(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([
            ['image_id' => '10', 'location_id' => '1'],
            ['image_id' => '20', 'location_id' => '2'],
        ]);

        $definition = new ProfilePictureImageTypeDefinition($this->repo(), $conn, $this->createStub(UserRepository::class));

        static::assertSame([['imageId' => 10, 'locationId' => 1], ['imageId' => 20, 'locationId' => 2]], $definition->discoverImageIds());
    }

    public function testProfilePictureLocateResolvesUser(): void
    {
        $image = $this->createStub(Image::class);

        $user = $this->createStub(User::class);
        $user->method('getName')->willReturn('Alice');
        $user->method('getId')->willReturn(3);

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $definition = new ProfilePictureImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $userRepo);

        static::assertSame(['label' => 'Profile picture: Alice', 'route' => 'app_admin_member_edit', 'params' => ['id' => 3]], $definition->locate($image));
    }

    public function testProfilePictureLocateReturnsNullWhenNoUser(): void
    {
        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);

        $definition = new ProfilePictureImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $userRepo);

        static::assertNull($definition->locate($this->createStub(Image::class)));
    }

    // =========================================================================
    // EventTeaser
    // =========================================================================

    public function testEventTeaserIdentityAndSizes(): void
    {
        $definition = new EventTeaserImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $this->createStub(EventRepository::class));

        static::assertSame(ImageType::EventTeaser, $definition->getType());
        static::assertSame([[1024, 768], [600, 400], [350, 263], [210, 140], [100, 100], [50, 50]], $definition->thumbnailSizes());
        static::assertSame(['route' => 'app_admin_event_edit', 'params' => ['id' => 7]], $definition->getEditLink(7));
    }

    public function testEventTeaserDiscoverImageIds(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([['image_id' => '3', 'location_id' => '99']]);

        $definition = new EventTeaserImageTypeDefinition($this->repo(), $conn, $this->createStub(EventRepository::class));

        static::assertSame([['imageId' => 3, 'locationId' => 99]], $definition->discoverImageIds());
    }

    public function testEventTeaserLocateResolvesEvent(): void
    {
        $event = new class extends EventStub {
            public function getTitle(string $language): string
            {
                return 'Go Tournament';
            }
        };
        $event->setId(11);

        $eventRepo = $this->createStub(EventRepository::class);
        $eventRepo->method('findOneBy')->willReturn($event);

        $definition = new EventTeaserImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $eventRepo);

        $result = $definition->locate($this->createStub(Image::class));
        static::assertSame('Event preview: Go Tournament', $result['label']);
        static::assertSame('app_admin_event_edit', $result['route']);
        static::assertSame(['id' => 11], $result['params']);
    }

    // =========================================================================
    // EventUpload
    // =========================================================================

    public function testEventUploadIdentityAndSizes(): void
    {
        $definition = new EventUploadImageTypeDefinition($this->repo(), $this->createStub(Connection::class));

        static::assertSame(ImageType::EventUpload, $definition->getType());
        static::assertSame([[1024, 768], [350, 263], [210, 140], [100, 100], [50, 50]], $definition->thumbnailSizes());
        static::assertSame(['route' => 'app_admin_event_edit', 'params' => ['id' => 12]], $definition->getEditLink(12));
    }

    public function testEventUploadDiscoverImageIds(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([['image_id' => '55', 'location_id' => '8']]);

        $definition = new EventUploadImageTypeDefinition($this->repo(), $conn);

        static::assertSame([['imageId' => 55, 'locationId' => 8]], $definition->discoverImageIds());
    }

    public function testEventUploadLocateUsesImageEventFk(): void
    {
        $event = new class extends EventStub {
            public function getTitle(string $language): string
            {
                return 'Summer Party';
            }
        };
        $event->setId(7);

        $image = $this->createStub(Image::class);
        $image->method('getEvent')->willReturn($event);

        $definition = new EventUploadImageTypeDefinition($this->repo(), $this->createStub(Connection::class));

        $result = $definition->locate($image);
        static::assertSame('Event upload: Summer Party', $result['label']);
        static::assertSame('app_admin_event_edit', $result['route']);
        static::assertSame(['id' => 7], $result['params']);
    }

    public function testEventUploadLocateReturnsNullWithoutEventFk(): void
    {
        $image = $this->createStub(Image::class);
        $image->method('getEvent')->willReturn(null);

        $definition = new EventUploadImageTypeDefinition($this->repo(), $this->createStub(Connection::class));

        static::assertNull($definition->locate($image));
    }

    // =========================================================================
    // CmsBlock / CmsCardImage / CmsGallery
    // =========================================================================

    public function testCmsBlockIdentityAndSizes(): void
    {
        $definition = new CmsBlockImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $this->createStub(CmsBlockRepository::class));

        static::assertSame(ImageType::CmsBlock, $definition->getType());
        static::assertSame([[432, 432], [350, 350], [80, 80], [100, 100], [50, 50]], $definition->thumbnailSizes());
        static::assertSame(['route' => 'app_admin_cms_block_edit', 'params' => ['blockId' => 3]], $definition->getEditLink(3));
    }

    public function testCmsBlockDiscoverImageIds(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([['image_id' => '4', 'location_id' => '11']]);

        $definition = new CmsBlockImageTypeDefinition($this->repo(), $conn, $this->createStub(CmsBlockRepository::class));

        static::assertSame([['imageId' => 4, 'locationId' => 11]], $definition->discoverImageIds());
    }

    public function testCmsBlockLocateResolvesBlockPage(): void
    {
        $page = $this->createStub(Cms::class);
        $page->method('getId')->willReturn(4);

        $block = $this->createStub(CmsBlock::class);
        $block->method('getPage')->willReturn($page);

        $cmsBlockRepo = $this->createStub(CmsBlockRepository::class);
        $cmsBlockRepo->method('findOneBy')->willReturn($block);

        $definition = new CmsBlockImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $cmsBlockRepo);

        $result = $definition->locate($this->createStub(Image::class));
        static::assertSame('CMS block on page #4', $result['label']);
        static::assertSame('app_admin_cms_edit', $result['route']);
        static::assertSame(['id' => 4], $result['params']);
    }

    public function testCmsBlockLocateReturnsNullWhenPageMissing(): void
    {
        $block = $this->createStub(CmsBlock::class);
        $block->method('getPage')->willReturn(null);

        $cmsBlockRepo = $this->createStub(CmsBlockRepository::class);
        $cmsBlockRepo->method('findOneBy')->willReturn($block);

        $definition = new CmsBlockImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $cmsBlockRepo);

        static::assertNull($definition->locate($this->createStub(Image::class)));
    }

    public function testCmsCardImageIdentitySizesAndDiscovery(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([
            ['id' => '3', 'json' => json_encode(['cards' => [['image' => ['id' => 20]], ['image' => ['id' => 21]]]])],
        ]);

        $definition = new CmsCardImageImageTypeDefinition($this->repo(), $conn, $this->createStub(CmsBlockRepository::class));

        static::assertSame(ImageType::CmsCardImage, $definition->getType());
        static::assertSame([[600, 400], [350, 233], [300, 200], [100, 100], [50, 50]], $definition->thumbnailSizes());
        static::assertSame([['imageId' => 20, 'locationId' => 3], ['imageId' => 21, 'locationId' => 3]], $definition->discoverImageIds());
    }

    public function testCmsGalleryIdentitySizesAndDiscovery(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturn([
            ['id' => '7', 'json' => json_encode(['images' => [['id' => 10], ['id' => 11]]])],
        ]);

        $definition = new CmsGalleryImageTypeDefinition($this->repo(), $conn, $this->createStub(CmsBlockRepository::class));

        static::assertSame(ImageType::CmsGallery, $definition->getType());
        static::assertSame([[1024, 768], [350, 263], [210, 140], [100, 100], [50, 50]], $definition->thumbnailSizes());
        static::assertSame([['imageId' => 10, 'locationId' => 7], ['imageId' => 11, 'locationId' => 7]], $definition->discoverImageIds());
    }

    // =========================================================================
    // LanguageTile
    // =========================================================================

    public function testLanguageTileIdentitySizesAndEditLink(): void
    {
        $definition = new LanguageTileImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $this->createStub(LanguageRepository::class));

        static::assertSame(ImageType::LanguageTile, $definition->getType());
        static::assertSame([[600, 400], [350, 233], [300, 200], [100, 100], [50, 50]], $definition->thumbnailSizes());
        static::assertSame(['route' => 'app_admin_language_edit', 'params' => ['id' => 9]], $definition->getEditLink(9));
    }

    public function testLanguageTileLocateResolvesLanguage(): void
    {
        $language = $this->createStub(Language::class);
        $language->method('getName')->willReturn('German');
        $language->method('getId')->willReturn(2);

        $languageRepo = $this->createStub(LanguageRepository::class);
        $languageRepo->method('findOneBy')->willReturn($language);

        $definition = new LanguageTileImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $languageRepo);

        static::assertSame(
            ['label' => 'Language tile: German', 'route' => 'app_admin_language_edit', 'params' => ['id' => 2]],
            $definition->locate($this->createStub(Image::class)),
        );
    }

    // =========================================================================
    // SiteLogo (singleton config, Fit)
    // =========================================================================

    public function testSiteLogoIdentitySizesAndFitMode(): void
    {
        $definition = new SiteLogoImageTypeDefinition($this->repo(), $this->createStub(Connection::class));

        static::assertSame(ImageType::SiteLogo, $definition->getType());
        static::assertSame(ImageFitMode::Fit, $definition->fitMode());
        static::assertSame([[400, 400], [350, 350], [100, 100], [50, 50]], $definition->thumbnailSizes());
        static::assertSame(['route' => 'app_admin_system_theme', 'params' => []], $definition->getEditLink(0));
    }

    public function testSiteLogoDiscoverAndLocateMatchConfiguredImage(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAssociative')->willReturn(['value' => '42']);

        $image = $this->createStub(Image::class);
        $image->method('getId')->willReturn(42);

        $definition = new SiteLogoImageTypeDefinition($this->repo(), $conn);

        static::assertSame([['imageId' => 42, 'locationId' => 0]], $definition->discoverImageIds());
        static::assertSame(['label' => 'Site logo', 'route' => 'app_admin_system_theme', 'params' => []], $definition->locate($image));
    }

    public function testSiteLogoLocateReturnsNullForNonConfiguredImage(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAssociative')->willReturn(['value' => '42']);

        $image = $this->createStub(Image::class);
        $image->method('getId')->willReturn(99);

        $definition = new SiteLogoImageTypeDefinition($this->repo(), $conn);

        static::assertNull($definition->locate($image));
    }

    // =========================================================================
    // WebsiteImage (singleton config)
    // =========================================================================

    public function testWebsiteImageIdentitySizesAndEditLink(): void
    {
        $definition = new WebsiteImageImageTypeDefinition($this->repo(), $this->createStub(Connection::class));

        static::assertSame(ImageType::WebsiteImage, $definition->getType());
        static::assertSame([[1200, 630], [350, 184], [100, 100], [50, 50]], $definition->thumbnailSizes());
        static::assertSame(['route' => 'app_admin_system_config', 'params' => []], $definition->getEditLink(0));
    }

    public function testWebsiteImageDiscoverEmptyWhenUnset(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAssociative')->willReturn(false);

        $definition = new WebsiteImageImageTypeDefinition($this->repo(), $conn);

        static::assertSame([], $definition->discoverImageIds());
    }
}
