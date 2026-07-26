<?php declare(strict_types=1);

namespace App\Item;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class FilterService
{
    /**
     * @param iterable<FilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(FilterInterface::class)]
        private iterable $filters,
    ) {}

    /** @return list<int>|null */
    public function getAllowedItemIds(string $itemType): ?array
    {
        $result = null;

        foreach ($this->filters as $filter) {
            $ids = $filter->getAllowedItemIds($itemType);
            if ($ids === null) {
                continue;
            }

            $result = $result === null ? array_values($ids) : array_values(array_intersect($result, $ids));
        }

        return $result;
    }
}
