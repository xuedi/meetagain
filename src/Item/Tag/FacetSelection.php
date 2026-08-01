<?php declare(strict_types=1);

namespace App\Item\Tag;

final readonly class FacetSelection
{
    /** @param list<int> $tags */
    public function __construct(
        public array $tags = [],
    ) {}

    public function withTagToggled(int $tagId): self
    {
        if (in_array($tagId, $this->tags, true)) {
            return new self(array_values(array_filter($this->tags, static fn(int $id): bool => $id !== $tagId)));
        }

        return new self([...$this->tags, $tagId]);
    }

    public function hasTag(int $tagId): bool
    {
        return in_array($tagId, $this->tags, true);
    }

    public function isEmpty(): bool
    {
        return $this->tags === [];
    }

    public function count(): int
    {
        return count($this->tags);
    }

    /** @return array<string, list<int>> */
    public function toQuery(): array
    {
        return $this->tags === [] ? [] : ['tag' => $this->tags];
    }
}
