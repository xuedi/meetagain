<?php declare(strict_types=1);

namespace Tests\Plugin\Dinnerclub\Unit\Service\ImageTypes;

use App\Entity\Image;
use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use App\Repository\ImageLocationRepository;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Plugin\Dinnerclub\Entity\Dish;
use Plugin\Dinnerclub\Repository\DishRepository;
use Plugin\Dinnerclub\Service\ImageTypes\DishImageTypeDefinition;

class DishImageTypeDefinitionTest extends TestCase
{
    private function repo(): ImageLocationRepository
    {
        return $this->createStub(ImageLocationRepository::class);
    }

    public function testIdentitySizesFitModeAndEditLink(): void
    {
        $definition = new DishImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $this->createStub(DishRepository::class));

        static::assertSame(ImageType::PluginDish, $definition->getType());
        static::assertSame(ImageFitMode::Crop, $definition->fitMode());
        static::assertSame([[1024, 768], [600, 400], [400, 400], [350, 263], [100, 100], [50, 50]], $definition->thumbnailSizes());
        static::assertSame(['route' => 'plugin_dinnerclub_item_show', 'params' => ['id' => 7]], $definition->getEditLink(7));
    }

    public function testDiscoverMergesGalleryAndPreviewImages(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchAllAssociative')->willReturnOnConsecutiveCalls(
            [['image_id' => '1', 'location_id' => '5']], // gallery
            [['image_id' => '2', 'location_id' => '5']], // preview
        );

        $definition = new DishImageTypeDefinition($this->repo(), $conn, $this->createStub(DishRepository::class));

        static::assertSame(
            [['imageId' => 1, 'locationId' => 5], ['imageId' => 2, 'locationId' => 5]],
            $definition->discoverImageIds(),
        );
    }

    public function testLocateResolvesPreviewDish(): void
    {
        $dish = $this->createStub(Dish::class);
        $dish->method('getAnyTranslatedName')->willReturn('Pad Thai');
        $dish->method('getId')->willReturn(8);

        $dishRepo = $this->createStub(DishRepository::class);
        $dishRepo->method('findOneBy')->willReturn($dish);

        $definition = new DishImageTypeDefinition($this->repo(), $this->createStub(Connection::class), $dishRepo);

        static::assertSame(
            ['label' => 'Dish: Pad Thai', 'route' => 'plugin_dinnerclub_item_show', 'params' => ['id' => 8]],
            $definition->locate($this->createStub(Image::class)),
        );
    }

    public function testLocateReturnsNullWhenNoDishFound(): void
    {
        $conn = $this->createStub(Connection::class);
        $conn->method('fetchOne')->willReturn(false);

        $dishRepo = $this->createStub(DishRepository::class);
        $dishRepo->method('findOneBy')->willReturn(null);

        $definition = new DishImageTypeDefinition($this->repo(), $conn, $dishRepo);

        static::assertNull($definition->locate($this->createStub(Image::class)));
    }
}
