<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

final readonly class FacetCounts
{
    /**
     * @param array<int, int> $categories category id => items it would yield
     * @param array<int, int> $tags       tag id => items it would yield
     */
    public function __construct(
        public array $categories,
        public array $tags,
        public int $total,
        public int $shown,
    ) {}

    public function forCategory(int $categoryId): int
    {
        return $this->categories[$categoryId] ?? 0;
    }

    public function forTag(int $tagId): int
    {
        return $this->tags[$tagId] ?? 0;
    }
}
