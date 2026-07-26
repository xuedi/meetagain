<?php declare(strict_types=1);

namespace Tests\Unit\Service\Media\ImageTypes;

use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\Service\Media\ImageTypes\ImageTypeDefinitionInterface;
use App\Service\Media\ImageTypes\ImageTypeRegistry;
use App\Service\Media\ThumbnailSizeFormat;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use RuntimeException;

class ImageTypeRegistryTest extends TestCase
{
    private const int FREE = ImageTypeDefinitionInterface::FREE_AXIS;

    private function format(): ThumbnailSizeFormat
    {
        return new ThumbnailSizeFormat();
    }

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
        $registry = new ImageTypeRegistry([$definition], $this->format());

        static::assertSame($definition, $registry->get(ImageType::ProfilePicture));
    }

    public function testGetThrowsForUnregisteredType(): void
    {
        $registry = new ImageTypeRegistry([], $this->format());

        $this->expectException(RuntimeException::class);
        $registry->get(ImageType::ProfilePicture);
    }

    public function testConstructorThrowsOnDuplicateType(): void
    {
        $this->expectException(RuntimeException::class);
        new ImageTypeRegistry([
            $this->definition(ImageType::ProfilePicture),
            $this->definition(ImageType::ProfilePicture),
        ], $this->format());
    }

    public function testGetThumbnailSizesDelegatesToDefinition(): void
    {
        $registry = new ImageTypeRegistry([$this->definition(ImageType::ProfilePicture, [[400, 400], [100, 100], [50, 50]])], $this->format());

        static::assertSame([[400, 400], [100, 100], [50, 50]], $registry->getThumbnailSizes(ImageType::ProfilePicture));
    }

    public function testGetFitModeDelegatesToDefinition(): void
    {
        $registry = new ImageTypeRegistry([$this->definition(ImageType::SiteLogo, [[350, 350]], ImageFitMode::Fit)], $this->format());

        static::assertSame(ImageFitMode::Fit, $registry->getFitMode(ImageType::SiteLogo));
    }

    public function testGetAdminPreviewSizeReturnsThe350WidthEntry(): void
    {
        $registry = new ImageTypeRegistry([$this->definition(ImageType::EventTeaser, [[1024, 768], [350, 263], [100, 100]])], $this->format());

        static::assertSame('350x263', $registry->getAdminPreviewSize(ImageType::EventTeaser));
    }

    public function testGetAdminPreviewSizeReturnsTheFreeRatioFormWhenTheHeightIsFree(): void
    {
        $registry = new ImageTypeRegistry([$this->definition(ImageType::SiteLogo, [[self::FREE, 120], [350, self::FREE]])], $this->format());

        static::assertSame('w350', $registry->getAdminPreviewSize(ImageType::SiteLogo));
    }

    public function testGetAdminPreviewSizeThrowsWhenNo350WidthEntry(): void
    {
        $registry = new ImageTypeRegistry([$this->definition(ImageType::GroupLogo, [[400, 400], [100, 100], [50, 50]])], $this->format());

        $this->expectException(RuntimeException::class);
        $registry->getAdminPreviewSize(ImageType::GroupLogo);
    }

    public function testGetThumbnailSizeListNamesTheFixedAxisOfAFreeRatioSize(): void
    {
        $registry = new ImageTypeRegistry([$this->definition(ImageType::SiteLogo, [[self::FREE, 120], [350, self::FREE], [50, 50]])], $this->format());

        static::assertSame(['h120' => 0, 'w350' => 0, '50x50' => 0], $registry->getThumbnailSizeList());
    }

    /**
     * @param array<int, array{0: int, 1: int}> $sizes
     */
    #[DataProvider('provideMalformedSizes')]
    public function testConstructorRejectsMalformedSizes(array $sizes): void
    {
        $this->expectException(RuntimeException::class);
        new ImageTypeRegistry([$this->definition(ImageType::SiteLogo, $sizes)], $this->format());
    }

    public static function provideMalformedSizes(): iterable
    {
        yield 'both axes free' => [[[self::FREE, self::FREE]]];
        yield 'free axis plus a zero axis' => [[[self::FREE, 0]]];
        yield 'zero width' => [[[0, 120]]];
        yield 'negative value that is not the sentinel' => [[[-2, 120]]];
        yield 'malformed entry beside valid ones' => [[[840, 120], [350, -7]]];
    }

    public function testConstructorAcceptsExactlyOneFreeAxis(): void
    {
        $registry = new ImageTypeRegistry([$this->definition(ImageType::SiteLogo, [[self::FREE, 120], [350, self::FREE]])], $this->format());

        static::assertSame([[self::FREE, 120], [350, self::FREE]], $registry->getThumbnailSizes(ImageType::SiteLogo));
    }

    public function testGetThumbnailSizeListIsTheUnionAcrossAllDefinitions(): void
    {
        $registry = new ImageTypeRegistry([
            $this->definition(ImageType::ProfilePicture, [[400, 400], [100, 100], [50, 50]]),
            $this->definition(ImageType::EventTeaser, [[1024, 768], [400, 400], [50, 50]]),
        ], $this->format());

        static::assertSame(['400x400' => 0, '100x100' => 0, '50x50' => 0, '1024x768' => 0], $registry->getThumbnailSizeList());
    }

    public function testAllReturnsEveryRegisteredDefinition(): void
    {
        $first = $this->definition(ImageType::ProfilePicture);
        $second = $this->definition(ImageType::EventTeaser);

        $registry = new ImageTypeRegistry([$first, $second], $this->format());

        static::assertSame([$first, $second], $registry->all());
    }
}
