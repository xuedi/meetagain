<?php declare(strict_types=1);

namespace Tests\Unit\Item\Portability;

use App\Item\Portability\ItemTaxonomyPortability;
use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\CategorizableTypeRegistry;
use App\Item\Taxonomy\ItemTaxonomyService;
use App\Item\Taxonomy\TaxonomyConfig;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;
use PHPUnit\Framework\TestCase;

class ItemTaxonomyPortabilityTest extends TestCase
{
    /** A legacy "no category" row carries id 0, which is not a definition id and must not travel. */
    public function testExportReadsBothAssignmentTablesKeyedBySourceItemId(): void
    {
        // Arrange
        $categoryRepo = $this->createStub(ItemCategoryAssignmentRepository::class);
        $categoryRepo->method('categoriesForItems')->willReturn([12 => 3, 13 => 0]);
        $tagRepo = $this->createStub(ItemTagAssignmentRepository::class);
        $tagRepo->method('tagIdsForItems')->willReturn([12 => [2, 5]]);

        $portability = $this->makePortability(null, $categoryRepo, $tagRepo);

        // Act
        $block = $portability->export('dish', [12]);

        // Assert
        self::assertSame(['categories' => [12 => 3], 'tags' => [12 => [2, 5]]], $block);
    }

    public function testImportRekeysThroughTheRefMap(): void
    {
        // Arrange
        $categoryWrites = [];
        $tagWrites = [];
        $service = $this->createStub(ItemTaxonomyService::class);
        $service->method('getCategory')->willReturn(null);
        $service->method('getTagIds')->willReturn([]);
        $service->method('setCategory')->willReturnCallback(static function (string $type, int $id, ?int $category) use (&$categoryWrites): void {
            $categoryWrites[] = [$type, $id, $category];
        });
        $service->method('setTags')->willReturnCallback(static function (string $type, int $id, array $tags) use (&$tagWrites): void {
            $tagWrites[] = [$type, $id, $tags];
        });

        $portability = $this->makePortability($this->taxonomy([3], [2, 5]), null, null, $service);
        $block = ['categories' => [12 => 3], 'tags' => [12 => [2, 5]]];

        // Act
        $dropped = $portability->import('dish', $block, [12 => 91]);

        // Assert
        self::assertSame(0, $dropped);
        self::assertSame([['dish', 91, 3]], $categoryWrites);
        self::assertSame([['dish', 91, [2, 5]]], $tagWrites);
    }

    public function testUndefinedCategoryAndTagIdsAreDroppedAndCounted(): void
    {
        // Arrange
        $service = $this->createStub(ItemTaxonomyService::class);
        $service->method('getCategory')->willReturn(null);
        $service->method('getTagIds')->willReturn([]);
        $portability = $this->makePortability($this->taxonomy([3], [2]), null, null, $service);
        $block = ['categories' => [12 => 99], 'tags' => [12 => [2, 98]]];

        // Act
        $dropped = $portability->import('dish', $block, [12 => 91]);

        // Assert - the unknown category and the unknown tag
        self::assertSame(2, $dropped);
    }

    public function testAssignmentOfARefMissingFromTheMapIsDropped(): void
    {
        // Arrange
        $portability = $this->makePortability($this->taxonomy([3], [2]));
        $block = ['categories' => [77 => 3], 'tags' => [77 => [2]]];

        // Act
        $dropped = $portability->import('dish', $block, [12 => 91]);

        // Assert
        self::assertSame(2, $dropped);
    }

    public function testExistingClassificationOnTheTargetIsNotOverwritten(): void
    {
        // Arrange
        $categoryWrites = 0;
        $mergedTags = null;
        $service = $this->createStub(ItemTaxonomyService::class);
        $service->method('getCategory')->willReturn(1);
        $service->method('getTagIds')->willReturn([9]);
        $service->method('setCategory')->willReturnCallback(static function () use (&$categoryWrites): void {
            ++$categoryWrites;
        });
        $service->method('setTags')->willReturnCallback(static function (string $type, int $id, array $tags) use (&$mergedTags): void {
            $mergedTags = $tags;
        });

        $portability = $this->makePortability($this->taxonomy([3], [2]), null, null, $service);

        // Act
        $portability->import('dish', ['categories' => [12 => 3], 'tags' => [12 => [2]]], [12 => 91]);

        // Assert
        self::assertSame(0, $categoryWrites);
        self::assertSame([9, 2], $mergedTags);
    }

    /**
     * @param list<int> $categoryIds
     * @param list<int> $tagIds
     */
    private function taxonomy(array $categoryIds, array $tagIds): TaxonomyConfig
    {
        return new TaxonomyConfig()
            ->setCategories(array_map(static fn(int $id): array => ['id' => $id, 'labels' => ['en' => 'c' . $id]], $categoryIds))
            ->setTags(array_map(static fn(int $id): array => ['id' => $id, 'labels' => ['en' => 't' . $id]], $tagIds));
    }

    private function makePortability(
        ?TaxonomyConfig $taxonomy = null,
        ?ItemCategoryAssignmentRepository $categoryRepo = null,
        ?ItemTagAssignmentRepository $tagRepo = null,
        ?ItemTaxonomyService $service = null,
    ): ItemTaxonomyPortability {
        $registry = $this->createStub(CategorizableTypeRegistry::class);
        if ($taxonomy !== null) {
            $provider = $this->createStub(CategorizableTypeProviderInterface::class);
            $provider->method('getTaxonomy')->willReturn($taxonomy);
            $registry->method('providerFor')->willReturn($provider);
        }

        return new ItemTaxonomyPortability(
            categoryRepo: $categoryRepo ?? $this->createStub(ItemCategoryAssignmentRepository::class),
            tagRepo: $tagRepo ?? $this->createStub(ItemTagAssignmentRepository::class),
            taxonomyService: $service ?? $this->createStub(ItemTaxonomyService::class),
            registry: $registry,
        );
    }
}
