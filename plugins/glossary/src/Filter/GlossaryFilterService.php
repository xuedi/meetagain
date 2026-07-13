<?php declare(strict_types=1);

namespace Plugin\Glossary\Filter;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composes all GlossaryFilterInterface implementations via AND-intersection. Returns null
 * when no implementation has an opinion, so with none registered the glossary is unfiltered.
 */
readonly class GlossaryFilterService
{
    /** @param iterable<GlossaryFilterInterface> $filters */
    public function __construct(
        #[AutowireIterator(GlossaryFilterInterface::class)]
        private iterable $filters,
    ) {}

    /** @return int[]|null */
    public function getAllowedGlossaryIds(): ?array
    {
        $result = null;

        foreach ($this->filters as $filter) {
            $ids = $filter->getAllowedGlossaryIds();
            if ($ids === null) {
                continue;
            }

            $result = $result === null ? $ids : array_values(array_intersect($result, $ids));
        }

        return $result;
    }
}
