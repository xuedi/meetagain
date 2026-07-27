<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

final readonly class FacetSelection
{
    /** @param list<int> $tags */
    public function __construct(
        public ?int $category = null,
        public array $tags = [],
    ) {}

    public function withCategory(?int $category): self
    {
        return new self($category, $this->tags);
    }

    public function withTagToggled(int $tagId): self
    {
        if (in_array($tagId, $this->tags, true)) {
            return new self($this->category, array_values(array_filter($this->tags, static fn(int $id): bool => $id !== $tagId)));
        }

        return new self($this->category, [...$this->tags, $tagId]);
    }

    public function hasTag(int $tagId): bool
    {
        return in_array($tagId, $this->tags, true);
    }

    public function isEmpty(): bool
    {
        return $this->category === null && $this->tags === [];
    }

    public function count(): int
    {
        return ($this->category === null ? 0 : 1) + count($this->tags);
    }

    /** @return array<string, int|list<int>> */
    public function toQuery(): array
    {
        $query = [];
        if ($this->category !== null) {
            $query['category'] = $this->category;
        }

        if ($this->tags !== []) {
            $query['tag'] = $this->tags;
        }

        return $query;
    }
}
