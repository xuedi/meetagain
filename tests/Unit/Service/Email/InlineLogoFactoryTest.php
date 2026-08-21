<?php declare(strict_types=1);

namespace Tests\Unit\Service\Email;

use App\Entity\Image;
use App\Repository\ImageRepository;
use App\Service\Email\InlineLogoFactory;
use App\Service\Media\ImageService;
use PHPUnit\Framework\TestCase;

final class InlineLogoFactoryTest extends TestCase
{
    public function testTheConfiguredLogoIsEmbeddedAsAnInlinePngPart(): void
    {
        // Arrange
        $imageRepository = $this->createStub(ImageRepository::class);
        $imageRepository->method('find')->willReturn(new Image());
        $imageService = $this->createStub(ImageService::class);
        $imageService->method('getSourcePath')->willReturn('/data/images/abc.png');
        $imageService->method('renderPng')->willReturn('png-bytes');

        // Act
        $part = new InlineLogoFactory($imageRepository, $imageService, '/app')->create(7);

        // Assert
        static::assertNotNull($part);
        static::assertSame(InlineLogoFactory::CID_NAME, $part->getName());
        static::assertSame('png', $part->getMediaSubtype());
        static::assertSame('inline', $part->getPreparedHeaders()->get('content-disposition')?->getBody());
        static::assertSame('png-bytes', $part->getBody());
    }

    public function testTheDefaultAssetIsUsedWhenNoLogoImageIsConfigured(): void
    {
        // Arrange
        $imageRepository = $this->createMock(ImageRepository::class);
        $imageRepository->expects($this->never())->method('find');
        $imageService = $this->createMock(ImageService::class);
        $imageService
            ->expects($this->once())
            ->method('renderPng')
            ->with('/app/assets/images/logo.webp', 120)
            ->willReturn('png-bytes');

        // Act
        $part = new InlineLogoFactory($imageRepository, $imageService, '/app')->create(null);

        // Assert
        static::assertNotNull($part);
    }

    public function testTheSameLogoIsRasterisedOnceAcrossAWholeSendRun(): void
    {
        // Arrange
        $imageRepository = $this->createStub(ImageRepository::class);
        $imageRepository->method('find')->willReturn(new Image());
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->once())->method('renderPng')->willReturn('png-bytes');
        $factory = new InlineLogoFactory($imageRepository, $imageService, '/app');

        // Act
        $first = $factory->create(7);
        $second = $factory->create(7);

        // Assert
        static::assertSame($first->getBody(), $second->getBody());
    }

    public function testAnUnreadableSourceProducesNoPartRatherThanABrokenOne(): void
    {
        // Arrange
        $imageRepository = $this->createStub(ImageRepository::class);
        $imageRepository->method('find')->willReturn(new Image());
        $imageService = $this->createStub(ImageService::class);
        $imageService->method('renderPng')->willReturn(null);

        // Act
        $part = new InlineLogoFactory($imageRepository, $imageService, '/app')->create(7);

        // Assert
        static::assertNull($part);
    }

    public function testAConfiguredLogoThatNoLongerExistsProducesNoPart(): void
    {
        // Arrange
        $imageRepository = $this->createStub(ImageRepository::class);
        $imageRepository->method('find')->willReturn(null);
        $imageService = $this->createMock(ImageService::class);
        $imageService->expects($this->never())->method('renderPng');

        // Act
        $part = new InlineLogoFactory($imageRepository, $imageService, '/app')->create(7);

        // Assert
        static::assertNull($part);
    }
}
