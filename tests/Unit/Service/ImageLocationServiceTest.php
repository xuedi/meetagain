<?php declare(strict_types=1);

namespace Tests\Unit\Service;

use App\Entity\Image;
use App\Entity\ImageLocation;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use App\Service\Media\ImageLocationService;
use App\Service\Media\ImageTypes\ImageTypeDefinitionInterface;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use RuntimeException;

class ImageLocationServiceTest extends TestCase
{
    private function createService(
        ?ImageLocationRepository $locationRepo = null,
        ?ImageTypeRegistry $registry = null,
        ?LoggerInterface $logger = null,
    ): ImageLocationService {
        return new ImageLocationService(
            locationRepository: $locationRepo ?? $this->createStub(ImageLocationRepository::class),
            registry: $registry ?? $this->createStub(ImageTypeRegistry::class),
            logger: $logger ?? $this->createStub(LoggerInterface::class),
        );
    }

    // ---- addLocation / removeLocation ----

    public function testAddLocationDelegatesToRepo(): void
    {
        // Arrange
        $repoMock = $this->createMock(ImageLocationRepository::class);
        $repoMock
            ->expects($this->once())
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
        $repoMock
            ->expects($this->once())
            ->method('deleteByTypeAndPairs')
            ->with(ImageType::ProfilePicture, [['imageId' => 5, 'locationId' => 7]]);

        $service = $this->createService(locationRepo: $repoMock);

        // Act
        $service->removeLocation(5, ImageType::ProfilePicture, 7);
    }

    // ---- discover() ----

    public function testDiscoverCallsSyncOnEveryDefinition(): void
    {
        // Arrange
        $definitionMock = $this->createMock(ImageTypeDefinitionInterface::class);
        $definitionMock->expects($this->once())->method('sync');

        $registry = $this->createStub(ImageTypeRegistry::class);
        $registry->method('all')->willReturn([$definitionMock]);

        $service = $this->createService(registry: $registry);

        // Act
        $service->discover();
    }

    public function testDiscoverLogsErrorWhenDefinitionThrowsAndDoesNotPropagate(): void
    {
        // Arrange
        $definition = $this->createStub(ImageTypeDefinitionInterface::class);
        $definition->method('sync')->willThrowException(new RuntimeException('oops'));

        $registry = $this->createStub(ImageTypeRegistry::class);
        $registry->method('all')->willReturn([$definition]);

        $loggerMock = $this->createMock(LoggerInterface::class);
        $loggerMock->expects($this->once())->method('error');

        $service = $this->createService(registry: $registry, logger: $loggerMock);

        // Act: must not throw
        $service->discover();
    }

    public function testDiscoverContinuesToNextDefinitionAfterFirstThrows(): void
    {
        // Arrange
        $failing = $this->createStub(ImageTypeDefinitionInterface::class);
        $failing->method('sync')->willThrowException(new RuntimeException('fail'));

        $working = $this->createMock(ImageTypeDefinitionInterface::class);
        $working->expects($this->once())->method('sync');

        $registry = $this->createStub(ImageTypeRegistry::class);
        $registry->method('all')->willReturn([$failing, $working]);

        $service = $this->createService(registry: $registry);

        // Act
        $service->discover();
    }

    // ---- resolveEditLink() ----

    public function testResolveEditLinkDelegatesToDefinition(): void
    {
        // Arrange
        $location = new ImageLocation();
        $location->setLocationType(ImageType::EventTeaser);
        $location->setLocationId(42);

        $definition = $this->createStub(ImageTypeDefinitionInterface::class);
        $definition->method('getEditLink')->willReturn(['route' => 'app_admin_event_edit', 'params' => ['id' => 42]]);

        $registry = $this->createStub(ImageTypeRegistry::class);
        $registry->method('get')->willReturn($definition);

        $service = $this->createService(registry: $registry);

        // Act
        $result = $service->resolveEditLink($location);

        // Assert
        static::assertSame(['route' => 'app_admin_event_edit', 'params' => ['id' => 42]], $result);
    }

    public function testResolveEditLinkReturnsNullWhenDefinitionHasNoLink(): void
    {
        // Arrange
        $location = new ImageLocation();
        $location->setLocationType(ImageType::DeveloperAppLogo);
        $location->setLocationId(1);

        $definition = $this->createStub(ImageTypeDefinitionInterface::class);
        $definition->method('getEditLink')->willReturn(null);

        $registry = $this->createStub(ImageTypeRegistry::class);
        $registry->method('get')->willReturn($definition);

        $service = $this->createService(registry: $registry);

        // Act & Assert
        static::assertNull($service->resolveEditLink($location));
    }

    // ---- locate() ----

    public function testLocateDelegatesToDefinition(): void
    {
        // Arrange
        $image = $this->createStub(Image::class);
        $image->method('getType')->willReturn(ImageType::ProfilePicture);

        $definition = $this->createStub(ImageTypeDefinitionInterface::class);
        $definition->method('locate')->willReturn(['label' => 'Profile picture: Alice', 'route' => 'app_admin_member_edit', 'params' => ['id' => 3]]);

        $registry = $this->createStub(ImageTypeRegistry::class);
        $registry->method('get')->willReturn($definition);

        $service = $this->createService(registry: $registry);

        // Act
        $result = $service->locate($image);

        // Assert
        static::assertSame(['label' => 'Profile picture: Alice', 'route' => 'app_admin_member_edit', 'params' => ['id' => 3]], $result);
    }

    public function testLocateReturnsNullWhenDefinitionCannotResolve(): void
    {
        // Arrange
        $image = $this->createStub(Image::class);
        $image->method('getType')->willReturn(ImageType::DeveloperAppLogo);

        $definition = $this->createStub(ImageTypeDefinitionInterface::class);
        $definition->method('locate')->willReturn(null);

        $registry = $this->createStub(ImageTypeRegistry::class);
        $registry->method('get')->willReturn($definition);

        $service = $this->createService(registry: $registry);

        // Act & Assert
        static::assertNull($service->locate($image));
    }
}
