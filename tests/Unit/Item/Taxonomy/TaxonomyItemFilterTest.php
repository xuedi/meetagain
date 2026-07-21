<?php declare(strict_types=1);

namespace Tests\Unit\Item\Taxonomy;

use App\Item\Taxonomy\CategorizableTypeProviderInterface;
use App\Item\Taxonomy\CategorizableTypeRegistry;
use App\Item\Taxonomy\TaxonomyItemFilter;
use App\Repository\ItemCategoryAssignmentRepository;
use App\Repository\ItemTagAssignmentRepository;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class TaxonomyItemFilterTest extends TestCase
{
    public function testNoOpinionWhenNoFacetPresent(): void
    {
        // Arrange
        $filter = $this->makeFilter('/dishes', $this->categoryRepo(), $this->tagRepo());

        // Act + Assert
        static::assertNull($filter->getAllowedItemIds('dish'));
    }

    public function testNoOpinionForUnregisteredType(): void
    {
        // Arrange: registry returns no provider for 'dish'
        $registry = $this->createStub(CategorizableTypeRegistry::class);
        $registry->method('providerFor')->willReturn(null);
        $stack = new RequestStack();
        $stack->push(Request::create('/dishes?category=3'));
        $filter = new TaxonomyItemFilter($stack, $registry, $this->categoryRepo(), $this->tagRepo());

        // Act + Assert
        static::assertNull($filter->getAllowedItemIds('dish'));
    }

    public function testCategoryFacetIsSingleEquality(): void
    {
        // Arrange
        $categoryRepo = $this->categoryRepo(['itemIdsWithCategory' => [10, 11]]);
        $filter = $this->makeFilter('/dishes?category=3', $categoryRepo, $this->tagRepo());

        // Act + Assert
        static::assertSame([10, 11], $filter->getAllowedItemIds('dish'));
    }

    public function testTagFacetIsAndIntersection(): void
    {
        // Arrange
        $tagRepo = $this->tagRepo(['itemIdsWithAllTags' => [11]]);
        $filter = $this->makeFilter('/dishes?tag[]=1&tag[]=2', $this->categoryRepo(), $tagRepo);

        // Act + Assert
        static::assertSame([11], $filter->getAllowedItemIds('dish'));
    }

    public function testCategoryAndTagsAndTogether(): void
    {
        // Arrange: category allows {10,11}, tags allow {11,12} -> intersection {11}
        $categoryRepo = $this->categoryRepo(['itemIdsWithCategory' => [10, 11]]);
        $tagRepo = $this->tagRepo(['itemIdsWithAllTags' => [11, 12]]);
        $filter = $this->makeFilter('/dishes?category=3&tag[]=1', $categoryRepo, $tagRepo);

        // Act + Assert
        static::assertSame([11], $filter->getAllowedItemIds('dish'));
    }

    private function makeFilter(string $uri, ItemCategoryAssignmentRepository $categoryRepo, ItemTagAssignmentRepository $tagRepo): TaxonomyItemFilter
    {
        $provider = $this->createStub(CategorizableTypeProviderInterface::class);
        $provider->method('supportsCategories')->willReturn(true);
        $provider->method('supportsTags')->willReturn(true);

        $registry = $this->createStub(CategorizableTypeRegistry::class);
        $registry->method('providerFor')->willReturn($provider);

        $stack = new RequestStack();
        $stack->push(Request::create($uri));

        return new TaxonomyItemFilter($stack, $registry, $categoryRepo, $tagRepo);
    }

    /** @param array<string, list<int>> $returns */
    private function categoryRepo(array $returns = []): ItemCategoryAssignmentRepository
    {
        $repo = $this->createStub(ItemCategoryAssignmentRepository::class);
        $repo->method('itemIdsWithCategory')->willReturn($returns['itemIdsWithCategory'] ?? []);

        return $repo;
    }

    /** @param array<string, list<int>> $returns */
    private function tagRepo(array $returns = []): ItemTagAssignmentRepository
    {
        $repo = $this->createStub(ItemTagAssignmentRepository::class);
        $repo->method('itemIdsWithAllTags')->willReturn($returns['itemIdsWithAllTags'] ?? []);

        return $repo;
    }
}
