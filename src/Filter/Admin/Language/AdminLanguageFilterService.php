<?php declare(strict_types=1);

namespace App\Filter\Admin\Language;

use App\Filter\Language\LanguageFilterResult;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composite admin language filter service.
 * Collects all registered AdminLanguageFilterInterface implementations.
 * Combines multiple filters using AND logic for language code restrictions.
 */
readonly class AdminLanguageFilterService
{
    /**
     * @param iterable<AdminLanguageFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(AdminLanguageFilterInterface::class)]
        private iterable $filters,
    ) {}

    /**
     * Get the combined language code filter from all registered filters.
     * Uses intersection (AND) logic: a language must pass ALL filters.
     */
    public function getLanguageCodeFilter(): LanguageFilterResult
    {
        $resultSet = null;
        $hasActiveFilter = false;

        foreach ($this->getSortedFilters() as $filter) {
            $filterResult = $filter->getLanguageCodeFilter();

            if ($filterResult === null) {
                continue;
            }

            $hasActiveFilter = true;

            if ($filterResult === []) {
                return LanguageFilterResult::emptyResult();
            }

            if ($resultSet === null) {
                $resultSet = $filterResult;
            } else {
                $resultSet = array_values(array_intersect($resultSet, $filterResult));
                if ($resultSet === []) {
                    return LanguageFilterResult::emptyResult();
                }
            }
        }

        return new LanguageFilterResult($resultSet, $hasActiveFilter);
    }

    /**
     * Check if a language is accessible according to all registered filters.
     * Any filter returning false will deny access.
     * Returns true only if all filters allow (or have no opinion).
     */
    public function isLanguageAccessible(string $code): bool
    {
        foreach ($this->getSortedFilters() as $filter) {
            $result = $filter->isLanguageAccessible($code);

            if ($result === false) {
                return false;
            }
        }

        return true;
    }

    /**
     * @return array<AdminLanguageFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort(
            $filters,
            static fn(
                AdminLanguageFilterInterface $a,
                AdminLanguageFilterInterface $b,
            ): int => $b->getPriority() <=> $a->getPriority(),
        );

        return $filters;
    }
}
