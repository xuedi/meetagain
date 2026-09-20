<?php declare(strict_types=1);

namespace Plugin\Dishes\Tests\Unit\Portability;

use App\Entity\Image;
use App\Entity\User;
use App\Portability\ImageImporter;
use App\Portability\ImageWriterInterface;
use App\Portability\ImportContext;
use App\Service\Media\ImageLocationService;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Plugin\Dishes\Entity\Dish;
use Plugin\Dishes\Entity\DishImage;
use Plugin\Dishes\Entity\DishTranslation;
use Plugin\Dishes\Portability\DishContributor;
use Plugin\Dishes\Repository\DishRepository;
use ReflectionProperty;

class DishContributorTest extends TestCase
{
    public function testExportCarriesTranslationsAndImagePaths(): void
    {
        // Arrange
        $dish = $this->dish(12, 'Arancini');
        $dish->setPhonetic('a-ran-chi-ni');
        $dish->setOrigin('Sicily');
        $dish->setPreviewImage(new Image()->setHash('preview'));

        $galleryImage = new DishImage();
        $galleryImage->setDish($dish);
        $galleryImage->setImage(new Image()->setHash('gallery'));
        $galleryImage->setSortOrder(4);
        $galleryImage->setCreatedAt(new DateTimeImmutable());
        $dish->getGalleryImages()->add($galleryImage);

        $repo = $this->createStub(DishRepository::class);
        $repo->method('findBy')->willReturn([$dish]);

        $images = $this->createStub(ImageWriterInterface::class);
        $images->method('addImage')->willReturnCallback(static fn(Image $image): string => 'images/' . $image->getHash() . '.jpg');

        $contributor = $this->contributor($this->createStub(EntityManagerInterface::class), $repo);

        // Act
        $rows = $contributor->exportItems([12], $images);

        // Assert
        self::assertCount(1, $rows);
        self::assertSame(12, $rows[0]['ref']);
        self::assertSame(['en' => ['name' => 'Arancini', 'description' => 'Fried', 'recipe' => null]], $rows[0]['translations']);
        self::assertSame('a-ran-chi-ni', $rows[0]['phonetic']);
        self::assertSame('images/preview.jpg', $rows[0]['preview_image']);
        self::assertArrayNotHasKey('gallery', $rows[0]);
    }

    public function testAnOlderArchiveStillBringsItsGalleryAlong(): void
    {
        // Arrange
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $imageImporter = $this->createStub(ImageImporter::class);
        $imageImporter->method('import')->willReturn(new Image());

        $contributor = $this->contributor($em, $this->createStub(DishRepository::class));
        $row = [
            'ref' => 12,
            'translations' => ['en' => ['name' => 'Arancini', 'description' => '']],
            'gallery' => [['file' => 'images/g.jpg', 'sort_order' => 3]],
        ];

        // Act
        $contributor->importItems([$row], new ImportContext($imageImporter, '/tmp', new User()));

        // Assert
        $galleryImages = array_values(array_filter($persisted, static fn(object $e): bool => $e instanceof DishImage));
        self::assertCount(1, $galleryImages);
        self::assertSame(3, $galleryImages[0]->getSortOrder());
    }

    public function testImportAlwaysCreatesAndMapsTheRef(): void
    {
        // Arrange
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
            if ($entity instanceof Dish) {
                new ReflectionProperty(Dish::class, 'id')->setValue($entity, 55);
            }
        });

        $contributor = $this->contributor($em, $this->createStub(DishRepository::class));
        $rows = [
            [
                'ref' => 12,
                'translations' => ['en' => ['name' => 'Arancini', 'description' => 'Fried', 'recipe' => 'Roll it']],
                'phonetic' => null,
                'origin' => 'Sicily',
                'pronunciation' => null,
                'preview_image' => null,
                'gallery' => [],
            ],
        ];

        // Act
        $result = $contributor->importItems($rows, $this->context());

        // Assert
        self::assertSame([12 => 55], $result->refToItemId);
        self::assertSame(1, $result->created);
        self::assertSame(0, $result->matched);

        $dishes = array_values(array_filter($persisted, static fn(object $e): bool => $e instanceof Dish));
        self::assertCount(1, $dishes);
        self::assertSame('Sicily', $dishes[0]->getOrigin());
        self::assertSame('Arancini', $dishes[0]->getTranslatedName('en'));
        self::assertSame('Roll it', $dishes[0]->getTranslatedRecipe('en'));
    }

    public function testImportingTheSameRowTwiceCreatesTwoDishes(): void
    {
        // Arrange
        $created = 0;
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$created): void {
            if ($entity instanceof Dish) {
                ++$created;
                new ReflectionProperty(Dish::class, 'id')->setValue($entity, 100 + $created);
            }
        });

        $contributor = $this->contributor($em, $this->createStub(DishRepository::class));
        $row = ['ref' => 12, 'translations' => ['en' => ['name' => 'Arancini', 'description' => '']]];

        // Act
        $contributor->importItems([$row], $this->context());
        $result = $contributor->importItems([$row], $this->context());

        // Assert
        self::assertSame(2, $created);
        self::assertSame(0, $result->matched);
    }

    private function dish(int $id, string $name): Dish
    {
        $dish = new Dish();
        new ReflectionProperty(Dish::class, 'id')->setValue($dish, $id);
        $dish->setCreatedAt(new DateTimeImmutable());

        $translation = new DishTranslation();
        $translation->setLanguage('en');
        $translation->setName($name);
        $translation->setDescription('Fried');
        $dish->addTranslation($translation);

        return $dish;
    }

    private function context(): ImportContext
    {
        return new ImportContext($this->createStub(ImageImporter::class), '/tmp', new User());
    }

    private function contributor(EntityManagerInterface $em, DishRepository $repo): DishContributor
    {
        return new DishContributor($em, $repo, $this->createStub(ImageLocationService::class));
    }
}
