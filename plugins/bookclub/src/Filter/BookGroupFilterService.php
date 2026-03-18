<?php declare(strict_types=1);

namespace Plugin\Bookclub\Filter;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composite filter service that collects all BookGroupFilterInterface implementations.
 * Returns null when no filter is active (single-tenant / main host = no restriction).
 */
readonly class BookGroupFilterService
{
    /**
     * @param iterable<BookGroupFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(BookGroupFilterInterface::class)]
        private iterable $filters,
    ) {}

    /**
     * @return int[]|null null = no filtering, array = restrict to these suggestion IDs
     */
    public function getAllowedSuggestionIds(): ?array
    {
        return $this->intersect(static fn(BookGroupFilterInterface $f) => $f->getAllowedSuggestionIds());
    }

    /**
     * @return int[]|null null = no filtering, array = restrict to these event IDs
     */
    public function getAllowedEventIds(): ?array
    {
        return $this->intersect(static fn(BookGroupFilterInterface $f) => $f->getAllowedEventIds());
    }

    /**
     * @return int[]|null null = no filtering, array = restrict to these book IDs
     */
    public function getAllowedBookIds(): ?array
    {
        return $this->intersect(static fn(BookGroupFilterInterface $f) => $f->getAllowedBookIds());
    }

    /**
     * @return int[]|null
     */
    private function intersect(callable $getter): ?array
    {
        $result = null;

        foreach ($this->filters as $filter) {
            $ids = $getter($filter);
            if ($ids === null) {
                continue;
            }

            $result = $result === null ? $ids : array_values(array_intersect($result, $ids));
        }

        return $result;
    }
}
