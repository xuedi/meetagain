<?php declare(strict_types=1);

namespace App\Filter\Attribution;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composes all registered ImageAttributionFilterInterface implementations with AND
 * (intersection) logic to produce the set of image IDs visible in the current context.
 */
readonly class ImageAttributionFilterService
{
    /**
     * @param iterable<ImageAttributionFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(ImageAttributionFilterInterface::class)]
        private iterable $filters,
    ) {}

    /**
     * @return array<int>|null null = no restriction, [] = block all, [id,...] = whitelist
     */
    public function getVisibleImageIdFilter(): ?array
    {
        $resultSet = null;

        foreach ($this->getSortedFilters() as $filter) {
            $filterResult = $filter->getVisibleImageIdFilter();

            if ($filterResult === null) {
                continue;
            }

            if ($filterResult === []) {
                return [];
            }

            if ($resultSet === null) {
                $resultSet = $filterResult;
                continue;
            }
            $resultSet = array_values(array_intersect($resultSet, $filterResult));
            if ($resultSet === []) {
                return [];
            }
        }

        return $resultSet;
    }

    /**
     * @return array<ImageAttributionFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort($filters, static fn(ImageAttributionFilterInterface $a, ImageAttributionFilterInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $filters;
    }
}
