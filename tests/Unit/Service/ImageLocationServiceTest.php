<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\CmsBlock;
use App\Entity\Cms;
use App\Entity\Image;
use App\Entity\ImageLocation;
use App\Entity\User;
use App\Enum\ImageType;
use App\Repository\CmsBlockRepository;
use App\Repository\EventRepository;
use App\Repository\ImageLocationRepository;
use App\Repository\UserRepository;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageLocations\ImageLocationProviderInterface;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Tests\Unit\Stubs\EventStub;

class ImageLocationServiceTest extends TestCase
{
    private function createService(
        ?UserRepository $userRepo = null,
        ?EventRepository $eventRepo = null,
        ?CmsBlockRepository $cmsBlockRepo = null,
        ?ImageLocationRepository $locationRepo = null,
        ?LoggerInterface $logger = null,
        iterable $providers = [],
    ): ImageLocationService {
        return new ImageLocationService(
            userRepository: $userRepo ?? $this->createStub(UserRepository::class),
            eventRepository: $eventRepo ?? $this->createStub(EventRepository::class),
            cmsBlockRepository: $cmsBlockRepo ?? $this->createStub(CmsBlockRepository::class),
            locationRepository: $locationRepo ?? $this->createStub(ImageLocationRepository::class),
            logger: $logger ?? $this->createStub(LoggerInterface::class),
            providers: $providers,
        );
    }

    // ---- addLocation / removeLocation ----

    public function testAddLocationDelegatesToRepo(): void
    {
        // Arrange
        $repoMock = $this->createMock(ImageLocationRepository::class);
        $repoMock->expects($this->once())
            ->method('insertForType')
            ->with(ImageType::EventTeaser, [['imageId' => 10, 'locationId' => 20]]);

        $service = $this->createService(locationRepo: $repoMock);

        // Act
        $service->addLocation(10, ImageType::EventTeaser, 20);
    }

    public function testRemoveLocationDelegatesToRepo(): void
    {
        // Arrange
        $repoMock = $this->createMock(ImageLocationRepository::class);
        $repoMock->expects($this->once())
            ->method('deleteByTypeAndPairs')
            ->with(ImageType::ProfilePicture, [['imageId' => 5, 'locationId' => 7]]);

        $service = $this->createService(locationRepo: $repoMock);

        // Act
        $service->removeLocation(5, ImageType::ProfilePicture, 7);
    }

    // ---- discover() ----

    public function testDiscoverCallsSyncOnProvider(): void
    {
        // Arrange
        $providerMock = $this->createMock(ImageLocationProviderInterface::class);
        $providerMock->expects($this->once())->method('sync');

        $service = $this->createService(providers: [$providerMock]);

        // Act
        $service->discover();
    }

    public function testDiscoverLogsErrorWhenProviderThrowsAndDoesNotPropagate(): void
    {
        // Arrange
        $providerMock = $this->createStub(ImageLocationProviderInterface::class);
        $providerMock->method('sync')->willThrowException(new \RuntimeException('oops'));

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');

        $service = $this->createService(logger: $loggerMock, providers: [$providerMock]);

        // Act: must not throw
        $service->discover();
    }

    public function testDiscoverContinuesToNextProviderAfterFirstThrows(): void
    {
        // Arrange
        $failingProvider = $this->createStub(ImageLocationProviderInterface::class);
        $failingProvider->method('sync')->willThrowException(new \RuntimeException('fail'));

        $workingProvider = $this->createMock(ImageLocationProviderInterface::class);
        $workingProvider->expects($this->once())->method('sync');

        $service = $this->createService(
            logger: $this->createStub(LoggerInterface::class),
            providers: [$failingProvider, $workingProvider],
        );

        // Act
        $service->discover();
    }

    // ---- resolveEditLink() ----

    public function testResolveEditLinkReturnsLinkFromMatchingProvider(): void
    {
        // Arrange
        $location = new ImageLocation();
        $location->setLocationType(ImageType::EventTeaser);
        $location->setLocationId(42);

        $providerMock = $this->createStub(ImageLocationProviderInterface::class);
        $providerMock->method('getType')->willReturn(ImageType::EventTeaser);
        $providerMock->method('getEditLink')->willReturn(['route' => 'app_admin_event_edit', 'params' => ['id' => 42]]);

        $service = $this->createService(providers: [$providerMock]);

        // Act
        $result = $service->resolveEditLink($location);

        // Assert
        static::assertSame(['route' => 'app_admin_event_edit', 'params' => ['id' => 42]], $result);
    }

    public function testResolveEditLinkReturnsNullWhenNoProviderMatches(): void
    {
        // Arrange
        $location = new ImageLocation();
        $location->setLocationType(ImageType::CmsBlock);
        $location->setLocationId(1);

        $providerMock = $this->createStub(ImageLocationProviderInterface::class);
        $providerMock->method('getType')->willReturn(ImageType::EventTeaser);

        $service = $this->createService(providers: [$providerMock]);

        // Act
        $result = $service->resolveEditLink($location);

        // Assert
        static::assertNull($result);
    }

    public function testResolveEditLinkSkipsNonMatchingAndUsesSecond(): void
    {
        // Arrange
        $location = new ImageLocation();
        $location->setLocationType(ImageType::ProfilePicture);
        $location->setLocationId(9);

        $firstProvider = $this->createStub(ImageLocationProviderInterface::class);
        $firstProvider->method('getType')->willReturn(ImageType::EventTeaser);

        $secondProvider = $this->createStub(ImageLocationProviderInterface::class);
        $secondProvider->method('getType')->willReturn(ImageType::ProfilePicture);
        $secondProvider->method('getEditLink')->willReturn(['route' => 'profile', 'params' => []]);

        $service = $this->createService(providers: [$firstProvider, $secondProvider]);

        // Act
        $result = $service->resolveEditLink($location);

        // Assert
        static::assertSame(['route' => 'profile', 'params' => []], $result);
    }

    // ---- locate(): event FK branch ----

    public function testLocateImageWithEventFkReturnsEventUploadLabel(): void
    {
        // Arrange: Image linked to an Event; Event::getName() doesn't exist on entity,
        // so we mock the Event via the Image mock
        $eventStub = new class extends EventStub {
            public function getTitle(string $language): string { return 'Summer Party'; }
        };
        $eventStub->setId(7);

        $imageStub = $this->createStub(Image::class);
        $imageStub->method('getEvent')->willReturn($eventStub);

        $service = $this->createService();

        // Act
        $result = $service->locate($imageStub);

        // Assert
        static::assertSame('Event upload: Summer Party', $result['label']);
        static::assertSame('app_admin_event_edit', $result['route']);
        static::assertSame(['id' => 7], $result['params']);
    }

    // ---- locate(): type dispatch ----

    public function testLocateEventUploadTypeWithNoEventFkReturnsNull(): void
    {
        // Arrange: ImageType::EventUpload, no event FK
        $imageStub = $this->createStub(Image::class);
        $imageStub->method('getEvent')->willReturn(null);
        $imageStub->method('getType')->willReturn(ImageType::EventUpload);

        $service = $this->createService();

        // Act & Assert
        static::assertNull($service->locate($imageStub));
    }

    // ---- locateProfilePicture (via locate) ----

    public function testLocateProfilePictureUserFoundReturnsLabel(): void
    {
        // Arrange
        $image = $this->createStub(Image::class);
        $image->method('getEvent')->willReturn(null);
        $image->method('getType')->willReturn(ImageType::ProfilePicture);

        $user = $this->createStub(User::class);
        $user->method('getName')->willReturn('Alice');
        $user->method('getId')->willReturn(3);

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn($user);

        $service = $this->createService(userRepo: $userRepo);

        // Act
        $result = $service->locate($image);

        // Assert
        static::assertSame('Profile picture: Alice', $result['label']);
        static::assertSame('app_admin_member_edit', $result['route']);
        static::assertSame(['id' => 3], $result['params']);
    }

    public function testLocateProfilePictureUserNotFoundReturnsNull(): void
    {
        // Arrange
        $image = $this->createStub(Image::class);
        $image->method('getEvent')->willReturn(null);
        $image->method('getType')->willReturn(ImageType::ProfilePicture);

        $userRepo = $this->createStub(UserRepository::class);
        $userRepo->method('findOneBy')->willReturn(null);

        $service = $this->createService(userRepo: $userRepo);

        // Act & Assert
        static::assertNull($service->locate($image));
    }

    // ---- locateEventTeaser (via locate) ----

    public function testLocateEventTeaserEventFoundReturnsLabel(): void
    {
        // Arrange
        $image = $this->createStub(Image::class);
        $image->method('getEvent')->willReturn(null);
        $image->method('getType')->willReturn(ImageType::EventTeaser);

        $event = new class extends EventStub {
            public function getTitle(string $language): string { return 'Go Tournament'; }
        };
        $event->setId(11);

        $eventRepo = $this->createStub(EventRepository::class);
        $eventRepo->method('findOneBy')->willReturn($event);

        $service = $this->createService(eventRepo: $eventRepo);

        // Act
        $result = $service->locate($image);

        // Assert
        static::assertSame('Event preview: Go Tournament', $result['label']);
        static::assertSame('app_admin_event_edit', $result['route']);
    }

    public function testLocateEventTeaserEventNotFoundReturnsNull(): void
    {
        // Arrange
        $image = $this->createStub(Image::class);
        $image->method('getEvent')->willReturn(null);
        $image->method('getType')->willReturn(ImageType::EventTeaser);

        $eventRepo = $this->createStub(EventRepository::class);
        $eventRepo->method('findOneBy')->willReturn(null);

        $service = $this->createService(eventRepo: $eventRepo);

        // Act & Assert
        static::assertNull($service->locate($image));
    }

    // ---- locateCmsBlock (via locate) ----

    public function testLocateCmsBlockBlockFoundWithPageReturnsLabel(): void
    {
        // Arrange
        $image = $this->createStub(Image::class);
        $image->method('getEvent')->willReturn(null);
        $image->method('getType')->willReturn(ImageType::CmsBlock);

        $page = $this->createStub(Cms::class);
        $page->method('getId')->willReturn(4);

        $block = $this->createStub(CmsBlock::class);
        $block->method('getPage')->willReturn($page);

        $cmsBlockRepo = $this->createStub(CmsBlockRepository::class);
        $cmsBlockRepo->method('findOneBy')->willReturn($block);

        $service = $this->createService(cmsBlockRepo: $cmsBlockRepo);

        // Act
        $result = $service->locate($image);

        // Assert
        static::assertSame('CMS block on page #4', $result['label']);
        static::assertSame('app_admin_cms_edit', $result['route']);
        static::assertSame(['id' => 4], $result['params']);
    }

    public function testLocateCmsBlockBlockFoundButPageNullReturnsNull(): void
    {
        // Arrange
        $image = $this->createStub(Image::class);
        $image->method('getEvent')->willReturn(null);
        $image->method('getType')->willReturn(ImageType::CmsGallery);

        $block = $this->createStub(CmsBlock::class);
        $block->method('getPage')->willReturn(null);

        $cmsBlockRepo = $this->createStub(CmsBlockRepository::class);
        $cmsBlockRepo->method('findOneBy')->willReturn($block);

        $service = $this->createService(cmsBlockRepo: $cmsBlockRepo);

        // Act & Assert
        static::assertNull($service->locate($image));
    }

    public function testLocateCmsBlockBlockNotFoundReturnsNull(): void
    {
        // Arrange
        $image = $this->createStub(Image::class);
        $image->method('getEvent')->willReturn(null);
        $image->method('getType')->willReturn(ImageType::CmsCardImage);

        $cmsBlockRepo = $this->createStub(CmsBlockRepository::class);
        $cmsBlockRepo->method('findOneBy')->willReturn(null);

        $service = $this->createService(cmsBlockRepo: $cmsBlockRepo);

        // Act & Assert
        static::assertNull($service->locate($image));
    }
}
