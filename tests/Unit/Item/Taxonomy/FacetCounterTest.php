<?php declare(strict_types=1);

namespace Tests\Unit\Item\Taxonomy;

use App\Item\ListProviderInterface;
use App\Item\ListRegistry;
use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\CategorizableTypeRegistry;
use App\Item\Taxonomy\Config;
use App\Item\Taxonomy\FacetCounter;
use App\Item\Taxonomy\FacetResolver;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;
use PHPUnit\Framework\MockObject\MockObject;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class FacetCounterTest extends TestCase
{
    public function testNoCountsForATypeWithoutAVocabulary(): void
    {
        // Arrange
        $counter = new FacetCounter(
            $this->listRegistry([1, 2]),
            $this->typeRegistry(Config::fromArray(['categoriesEnabled' => false, 'tagsEnabled' => false])),
            $this->resolver('/dishes'),
            $this->createStub(ItemCategoryAssignmentRepository::class),
            $this->createStub(ItemTagAssignmentRepository::class),
        );

        // Act + Assert
        static::assertNull($counter->counts('dish'));
    }

    public function testUnfacetedListCountsEveryVisibleItem(): void
    {
        // Arrange
        $categoryRepo = $this->createStub(ItemCategoryAssignmentRepository::class);
        $categoryRepo->method('countsByCategory')->willReturn([10 => 3, 11 => 1]);
        $tagRepo = $this->createStub(ItemTagAssignmentRepository::class);
        $tagRepo->method('countsByTag')->willReturn([7 => 2]);

        $counter = new FacetCounter(
            $this->listRegistry([1, 2, 3, 4]),
            $this->typeRegistry($this->fullTaxonomy()),
            $this->resolver('/dishes'),
            $categoryRepo,
            $tagRepo,
        );

        // Act
        $counts = $counter->counts('dish');

        // Assert
        static::assertNotNull($counts);
        static::assertSame(4, $counts->total);
        static::assertSame(4, $counts->shown);
        static::assertSame(3, $counts->forCategory(10));
        static::assertSame(2, $counts->forTag(7));
    }

    public function testAnOptionOutsideTheCountedSetYieldsZero(): void
    {
        // Arrange
        $categoryRepo = $this->createStub(ItemCategoryAssignmentRepository::class);
        $categoryRepo->method('countsByCategory')->willReturn([10 => 3]);
        $tagRepo = $this->createStub(ItemTagAssignmentRepository::class);
        $tagRepo->method('countsByTag')->willReturn([]);

        $counter = new FacetCounter(
            $this->listRegistry([1, 2, 3]),
            $this->typeRegistry($this->fullTaxonomy()),
            $this->resolver('/dishes'),
            $categoryRepo,
            $tagRepo,
        );

        // Act
        $counts = $counter->counts('dish');

        // Assert
        static::assertNotNull($counts);
        static::assertSame(0, $counts->forCategory(11));
        static::assertSame(0, $counts->forTag(7));
    }

    public function testCategoryCountsIgnoreTheCategoryFacetWhileTagCountsSeeIt(): void
    {
        // Arrange
        $categoryRepo = $this->categoryRepo();
        $categoryRepo->method('itemIdsWithCategory')->willReturn([3, 4]);
        $categoryRepo->expects(static::once())
            ->method('countsByCategory')
            ->with('dish', [2, 3])
            ->willReturn([10 => 1, 11 => 1]);

        $tagRepo = $this->tagRepo();
        $tagRepo->method('itemIdsWithAllTags')->willReturn([2, 3, 5]);
        $tagRepo->expects(static::once())
            ->method('countsByTag')
            ->with('dish', [3])
            ->willReturn([7 => 1, 8 => 1]);

        $counter = new FacetCounter(
            $this->listRegistry([1, 2, 3, 4]),
            $this->typeRegistry($this->fullTaxonomy()),
            $this->resolver('/dishes?category=10&tag[]=7'),
            $categoryRepo,
            $tagRepo,
        );

        // Act
        $counts = $counter->counts('dish');

        // Assert
        static::assertNotNull($counts);
        static::assertSame(4, $counts->total);
        static::assertSame(1, $counts->shown);
    }

    private function fullTaxonomy(): Config
    {
        return Config::fromArray([
            'categoriesEnabled' => true,
            'tagsEnabled' => true,
            'categories' => [['id' => 10, 'labels' => ['en' => 'Starter']], ['id' => 11, 'labels' => ['en' => 'Main']]],
            'tags' => [['id' => 7, 'labels' => ['en' => 'Spicy']], ['id' => 8, 'labels' => ['en' => 'Vegan']]],
        ]);
    }

    /** @param list<int> $itemIds */
    private function listRegistry(array $itemIds): ListRegistry
    {
        $provider = $this->createStub(ListProviderInterface::class);
        $provider->method('getItemIds')->willReturn($itemIds);

        $registry = $this->createStub(ListRegistry::class);
        $registry->method('providerFor')->willReturn($provider);

        return $registry;
    }

    private function typeRegistry(Config $taxonomy): CategorizableTypeRegistry
    {
        $provider = $this->createStub(CategorizableTypeProviderInterface::class);
        $provider->method('supportsCategories')->willReturn(true);
        $provider->method('supportsTags')->willReturn(true);
        $provider->method('getTaxonomy')->willReturn($taxonomy);

        $registry = $this->createStub(CategorizableTypeRegistry::class);
        $registry->method('providerFor')->willReturn($provider);

        return $registry;
    }

    private function resolver(string $uri): FacetResolver
    {
        $stack = new RequestStack();
        $stack->push(Request::create($uri));

        return new FacetResolver($stack);
    }

    /** @return ItemCategoryAssignmentRepository&MockObject */
    private function categoryRepo(): ItemCategoryAssignmentRepository
    {
        return $this->createMock(ItemCategoryAssignmentRepository::class);
    }

    /** @return ItemTagAssignmentRepository&MockObject */
    private function tagRepo(): ItemTagAssignmentRepository
    {
        return $this->createMock(ItemTagAssignmentRepository::class);
    }
}
