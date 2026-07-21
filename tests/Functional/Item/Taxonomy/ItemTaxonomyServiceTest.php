<?php declare(strict_types=1);

namespace Tests\Functional\Item\Taxonomy;

use App\Enum\ItemAction;
use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\CategorizableTypeRegistry;
use App\Item\Taxonomy\ItemTaxonomyService;
use App\Item\Taxonomy\TaxonomyAssignmentCleanupHandler;
use App\Item\Taxonomy\TaxonomyConfig;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;
use App\Service\Config\LanguageService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

/**
 * Exercises the taxonomy service and cleanup handler against the real assignment tables. The
 * categorizable registry is stubbed with a fixed taxonomy so the test does not depend on
 * request-scoped plugin-active resolution; everything else (repositories, entity manager, DB round
 * trip) is real.
 */
class ItemTaxonomyServiceTest extends KernelTestCase
{
    private const string TYPE = 'dish';

    private EntityManagerInterface $em;
    private ItemCategoryAssignmentRepository $categoryRepo;
    private ItemTagAssignmentRepository $tagRepo;
    private ItemTaxonomyService $service;

    protected function setUp(): void
    {
        self::bootKernel();
        $container = self::getContainer();
        $this->em = $container->get(EntityManagerInterface::class);
        $this->categoryRepo = $container->get(ItemCategoryAssignmentRepository::class);
        $this->tagRepo = $container->get(ItemTagAssignmentRepository::class);

        $this->service = new ItemTaxonomyService(
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

        // Act: an id absent from the definitions clears the assignment
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
        // Act: 999 is not a defined tag, so only 1 survives
        $this->service->setTags(self::TYPE, 90002, [1, 999]);

        // Assert
        static::assertSame([1], $this->service->getTagIds(self::TYPE, 90002));
    }

    public function testDeletedActionSweepsAssignments(): void
    {
        // Arrange
        $this->service->setCategory(self::TYPE, 90003, 1);
        $this->service->setTags(self::TYPE, 90003, [1, 2]);
        $handler = new TaxonomyAssignmentCleanupHandler($this->categoryRepo, $this->tagRepo);

        // Act
        $handler->onItemAction(ItemAction::Deleted, self::TYPE, 90003);

        // Assert
        static::assertNull($this->service->getCategory(self::TYPE, 90003));
        static::assertSame([], $this->service->getTagIds(self::TYPE, 90003));
    }

    private function registryWithTaxonomy(): CategorizableTypeRegistry
    {
        $taxonomy = (new TaxonomyConfig())
            ->setCategoriesEnabled(true)
            ->setTagsEnabled(true)
            ->setCategories([['id' => 1, 'labels' => ['en' => 'Spicy']]])
            ->setTags([['id' => 1, 'labels' => ['en' => 'Vegan']], ['id' => 2, 'labels' => ['en' => 'Quick']]]);

        $provider = $this->createStub(CategorizableTypeProviderInterface::class);
        $provider->method('getTaxonomy')->willReturn($taxonomy);

        $registry = $this->createStub(CategorizableTypeRegistry::class);
        $registry->method('providerFor')->willReturn($provider);

        return $registry;
    }
}
