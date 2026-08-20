<?php declare(strict_types=1);

namespace App\Filter\Admin\Support;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class AdminSupportListFilterService
{
    /**
     * @param iterable<AdminSupportListFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(AdminSupportListFilterInterface::class)]
        private iterable $filters,
    ) {}

    /**
     * @return array<int>|null null = unrestricted
     */
    public function getRequestIdFilter(): ?array
    {
        $allowed = null;

        foreach ($this->filters as $filter) {
            $ids = $filter->getRequestIdFilter();
            if ($ids === null) {
                continue;
            }

            if ($ids === []) {
                return [];
            }

            $allowed = $allowed === null ? $ids : array_values(array_intersect($allowed, $ids));
            if ($allowed === []) {
                return [];
            }
        }

        return $allowed;
    }
}
