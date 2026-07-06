<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media\ImageTypes;

use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\Service\Media\ImageTypes\ImageTypeDefinitionInterface;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ImageTypeRegistryTest extends TestCase
{
    /**
     * @param array<int, array{0: int, 1: int}> $sizes
     */
    private function definition(ImageType $type, array $sizes = [[350, 350]], ImageFitMode $fitMode = ImageFitMode::Crop): ImageTypeDefinitionInterface
    {
        $definition = $this->createStub(ImageTypeDefinitionInterface::class);
        $definition->method('getType')->willReturn($type);
        $definition->method('thumbnailSizes')->willReturn($sizes);
        $definition->method('fitMode')->willReturn($fitMode);

        return $definition;
    }

    public function testGetReturnsTheDefinitionForAType(): void
    {
        $definition = $this->definition(ImageType::ProfilePicture);
        $registry = new ImageTypeRegistry([$definition]);

        static::assertSame($definition, $registry->get(ImageType::ProfilePicture));
    }

    public function testGetThrowsForUnregisteredType(): void
    {
        $registry = new ImageTypeRegistry([]);

        $this->expectException(RuntimeException::class);
        $registry->get(ImageType::ProfilePicture);
    }

    public function testConstructorThrowsOnDuplicateType(): void
    {
        $this->expectException(RuntimeException::class);
        new ImageTypeRegistry([
            $this->definition(ImageType::ProfilePicture),
            $this->definition(ImageType::ProfilePicture),
        ]);
    }

    public function testGetThumbnailSizesDelegatesToDefinition(): void
    {
        $registry = new ImageTypeRegistry([$this->definition(ImageType::ProfilePicture, [[400, 400], [100, 100], [50, 50]])]);

        static::assertSame([[400, 400], [100, 100], [50, 50]], $registry->getThumbnailSizes(ImageType::ProfilePicture));
    }

    public function testGetFitModeDelegatesToDefinition(): void
    {
        $registry = new ImageTypeRegistry([$this->definition(ImageType::SiteLogo, [[350, 350]], ImageFitMode::Fit)]);

        static::assertSame(ImageFitMode::Fit, $registry->getFitMode(ImageType::SiteLogo));
    }

    public function testGetAdminPreviewSizeReturnsThe350WidthEntry(): void
    {
        $registry = new ImageTypeRegistry([$this->definition(ImageType::EventTeaser, [[1024, 768], [350, 263], [100, 100]])]);

        static::assertSame('350x263', $registry->getAdminPreviewSize(ImageType::EventTeaser));
    }

    public function testGetAdminPreviewSizeThrowsWhenNo350WidthEntry(): void
    {
        $registry = new ImageTypeRegistry([$this->definition(ImageType::GroupLogo, [[400, 400], [100, 100], [50, 50]])]);

        $this->expectException(RuntimeException::class);
        $registry->getAdminPreviewSize(ImageType::GroupLogo);
    }

    public function testGetThumbnailSizeListIsTheUnionAcrossAllDefinitions(): void
    {
        $registry = new ImageTypeRegistry([
            $this->definition(ImageType::ProfilePicture, [[400, 400], [100, 100], [50, 50]]),
            $this->definition(ImageType::EventTeaser, [[1024, 768], [400, 400], [50, 50]]),
        ]);

        static::assertSame(
            ['400x400' => 0, '100x100' => 0, '50x50' => 0, '1024x768' => 0],
            $registry->getThumbnailSizeList(),
        );
    }

    public function testIsValidThumbnailSize(): void
    {
        $registry = new ImageTypeRegistry([$this->definition(ImageType::ProfilePicture, [[400, 400], [100, 100], [50, 50]])]);

        static::assertTrue($registry->isValidThumbnailSize(ImageType::ProfilePicture, 400, 400));
        static::assertFalse($registry->isValidThumbnailSize(ImageType::ProfilePicture, 123, 456));
    }

    public function testAllReturnsEveryRegisteredDefinition(): void
    {
        $first = $this->definition(ImageType::ProfilePicture);
        $second = $this->definition(ImageType::EventTeaser);

        $registry = new ImageTypeRegistry([$first, $second]);

        static::assertSame([$first, $second], $registry->all());
    }
}
