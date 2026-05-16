<?php declare(strict_types=1);

namespace Plugin\Filmclub\Filter;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composes all FilmGroupFilterInterface implementations via AND-intersection.
 * Returns null when no implementation has an opinion on a given method.
 */
readonly class FilmGroupFilterService
{
    /** @param iterable<FilmGroupFilterInterface> $filters */
    public function __construct(
        #[AutowireIterator(FilmGroupFilterInterface::class)]
        private iterable $filters,
    ) {}

    /** @return int[]|null */
    public function getAllowedFilmIds(): ?array
    {
        return $this->intersect(static fn(FilmGroupFilterInterface $f) => $f->getAllowedFilmIds());
    }

    /** @return int[]|null */
    public function getAllowedSuggestionIds(): ?array
    {
        return $this->intersect(static fn(FilmGroupFilterInterface $f) => $f->getAllowedSuggestionIds());
    }

    /** @return int[]|null */
    public function getAllowedEventIds(): ?array
    {
        return $this->intersect(static fn(FilmGroupFilterInterface $f) => $f->getAllowedEventIds());
    }

    /** @return int[]|null */
    public function getAllowedPollIds(): ?array
    {
        return $this->intersect(static fn(FilmGroupFilterInterface $f) => $f->getAllowedPollIds());
    }

    /** @return int[]|null */
    public function getAllowedNoteIds(): ?array
    {
        return $this->intersect(static fn(FilmGroupFilterInterface $f) => $f->getAllowedNoteIds());
    }

    /** @return int[]|null */
    public function getAllowedWishlistEntryIds(): ?array
    {
        return $this->intersect(static fn(FilmGroupFilterInterface $f) => $f->getAllowedWishlistEntryIds());
    }

    /** @return int[]|null */
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
