<?php declare(strict_types=1);

namespace App\Item\Taxonomy;

final readonly class TagTree
{
    /** @param list<TagDefinition> $definitions */
    public function __construct(
        private array $definitions,
    ) {}

    /** @return list<int> the tag's ancestors, nearest parent first */
    public function ancestors(int $id): array
    {
        $parents = $this->parents();

        $ancestors = [];
        $current = $parents[$id] ?? null;
        while ($current !== null && !in_array($current, $ancestors, true) && $current !== $id) {
            $ancestors[] = $current;
            $current = $parents[$current] ?? null;
        }

        return $ancestors;
    }

    public function parent(int $id): ?int
    {
        return $this->parents()[$id] ?? null;
    }

    /** @return positive-int */
    public function depthOf(int $id): int
    {
        return count($this->ancestors($id)) + 1;
    }

    public function root(int $id): ?TagDefinition
    {
        $ancestors = $this->ancestors($id);
        $root = end($ancestors);

        return $root === false ? null : $this->definition($root);
    }

    /** @return list<TagDefinition> the tags directly below $parent, or the root tags for null */
    public function children(?int $parent): array
    {
        $children = [];
        foreach ($this->definitions as $definition) {
            if ($definition->parent !== $parent) {
                continue;
            }

            $children[] = $definition;
        }

        return $children;
    }

    /** @return list<TagDefinition> depth-first, every tag directly after its parent */
    public function ordered(): array
    {
        $ordered = [];
        $seen = [];
        $this->appendBranch(null, $ordered, $seen);
        foreach ($this->definitions as $definition) {
            if (isset($seen[$definition->id])) {
                continue;
            }

            $ordered[] = $definition;
        }

        return $ordered;
    }

    /**
     * @param  list<int> $ids
     * @return list<int> the given ids without the ones a deeper id in the set already implies
     */
    public function leafmost(array $ids): array
    {
        $implied = [];
        foreach ($ids as $id) {
            foreach ($this->ancestors($id) as $ancestor) {
                $implied[$ancestor] = true;
            }
        }

        return array_values(array_filter($ids, static fn(int $id): bool => !isset($implied[$id])));
    }

    public function hasBranches(): bool
    {
        foreach ($this->definitions as $definition) {
            if ($definition->parent !== null) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param list<TagDefinition> $ordered
     * @param array<int, true>    $seen
     */
    private function appendBranch(?int $parent, array &$ordered, array &$seen): void
    {
        foreach ($this->children($parent) as $child) {
            if (isset($seen[$child->id])) {
                continue;
            }

            $seen[$child->id] = true;
            $ordered[] = $child;
            $this->appendBranch($child->id, $ordered, $seen);
        }
    }

    /** @return array<int, ?int> */
    private function parents(): array
    {
        $parents = [];
        foreach ($this->definitions as $definition) {
            $parents[$definition->id] = $definition->parent;
        }

        return $parents;
    }

    private function definition(int $id): ?TagDefinition
    {
        foreach ($this->definitions as $definition) {
            if ($definition->id === $id) {
                return $definition;
            }
        }

        return null;
    }
}
