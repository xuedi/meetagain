<?php declare(strict_types=1);

namespace Tests\Functional\Item\Taxonomy;

use App\Enum\ItemAction;
use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\CategorizableTypeRegistry;
use App\Item\Taxonomy\TaxonomyService;
use App\Item\Taxonomy\AssignmentCleanupHandler;
use App\Item\Taxonomy\Config;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;
use App\Service\Config\LanguageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TaxonomyServiceTest extends KernelTestCase
{
    private const string TYPE = 'dish';

    private EntityManagerInterface $em;
    private ItemCategoryAssignmentRepository $categoryRepo;
    private ItemTagAssignmentRepository $tagRepo;
    private TaxonomyService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->categoryRepo = $container->get(ItemCategoryAssignmentRepository::class);
        $this->tagRepo = $container->get(ItemTagAssignmentRepository::class);

        $this->service = new TaxonomyService(
            $this->em,
            $this->categoryRepo,
            $this->tagRepo,
            $this->registryWithTaxonomy(),
            $container->get(LanguageService::class),
        );
    }

    public function testSetAndGetSingleCategory(): void
    {
        // Act
        $this->service->setCategory(self::TYPE, 90001, 1);

        // Assert
        static::assertSame(1, $this->service->getCategory(self::TYPE, 90001));
    }

    public function testUnknownCategoryIdIsRejectedAndClears(): void
    {
        // Arrange
        $this->service->setCategory(self::TYPE, 90001, 1);

        // Act
        $this->service->setCategory(self::TYPE, 90001, 999);

        // Assert
        static::assertNull($this->service->getCategory(self::TYPE, 90001));
    }

    public function testSetAndGetMultipleTags(): void
    {
        // Act
        $this->service->setTags(self::TYPE, 90002, [1, 2]);

        // Assert
        static::assertEqualsCanonicalizing([1, 2], $this->service->getTagIds(self::TYPE, 90002));
    }

    public function testUnknownTagIdsAreDropped(): void
    {
        // Act
        $this->service->setTags(self::TYPE, 90002, [1, 999]);

        // Assert
        static::assertSame([1], $this->service->getTagIds(self::TYPE, 90002));
    }

    public function testTaggingASubTagAlsoPersistsItsAncestors(): void
    {
        // Act
        $this->service->setTags(self::TYPE, 90004, [5]);

        // Assert
        static::assertEqualsCanonicalizing([3, 4, 5], $this->service->getTagIds(self::TYPE, 90004));
    }

    public function testDroppingAnAncestorLeavesTheAssignmentUntouched(): void
    {
        // Arrange
        $this->service->setTags(self::TYPE, 90005, [4]);

        // Act
        $this->service->setTags(self::TYPE, 90005, [4, 1]);

        // Assert
        static::assertEqualsCanonicalizing([1, 3, 4], $this->service->getTagIds(self::TYPE, 90005));
    }

    public function testTagLabelsNameTheDeepestTagOfEachBranchOnly(): void
    {
        // Arrange
        $this->service->setTags(self::TYPE, 90006, [5, 1]);

        // Act
        $labels = $this->service->getTagLabels(self::TYPE, 90006, 'en');

        // Assert
        static::assertEqualsCanonicalizing(['Vegan', 'Chicken'], $labels, 'Meat and Poultry rode along, but say nothing');
    }

    public function testDeletedActionSweepsAssignments(): void
    {
        // Arrange
        $this->service->setCategory(self::TYPE, 90003, 1);
        $this->service->setTags(self::TYPE, 90003, [1, 2]);
        $handler = new AssignmentCleanupHandler($this->categoryRepo, $this->tagRepo);

        // Act
        $handler->onItemAction(ItemAction::Deleted, self::TYPE, 90003);

        // Assert
        static::assertNull($this->service->getCategory(self::TYPE, 90003));
        static::assertSame([], $this->service->getTagIds(self::TYPE, 90003));
    }

    private function registryWithTaxonomy(): CategorizableTypeRegistry
    {
        $taxonomy = (new Config())
            ->setCategoriesEnabled(true)
            ->setTagsEnabled(true)
            ->setTagDepth(3)
            ->setCategories([['id' => 1, 'labels' => ['en' => 'Spicy']]])
            ->setTags([
                ['id' => 1, 'labels' => ['en' => 'Vegan']],
                ['id' => 2, 'labels' => ['en' => 'Quick']],
                ['id' => 3, 'labels' => ['en' => 'Meat']],
                ['id' => 4, 'labels' => ['en' => 'Poultry'], 'parent' => 3],
                ['id' => 5, 'labels' => ['en' => 'Chicken'], 'parent' => 4],
            ]);

        $provider = $this->createStub(CategorizableTypeProviderInterface::class);
        $provider->method('getTaxonomy')->willReturn($taxonomy);

        $registry = $this->createStub(CategorizableTypeRegistry::class);
        $registry->method('providerFor')->willReturn($provider);

        return $registry;
    }
}
