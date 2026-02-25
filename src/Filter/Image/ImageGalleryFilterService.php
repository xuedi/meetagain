<?php declare(strict_types=1);

namespace App\Filter\Image;

use App\Entity\Image;
use App\Entity\ImageType;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

/**
 * Composite image gallery filter service that collects all registered ImageGalleryFilterInterface implementations.
 * Combines multiple filters using AND logic for image ID restrictions.
 */
readonly class ImageGalleryFilterService
{
    /**
     * @param iterable<ImageGalleryFilterInterface> $filters
     */
    public function __construct(
        #[AutowireIterator(ImageGalleryFilterInterface::class)]
        private iterable $filters,
    ) {}

    /**
     * Get the combined image ID filter from all registered filters.
     * Uses intersection (AND) logic: an image must pass ALL filters.
     *
     * @return array<int>|null null = no filter, [] = block all, [id,...] = whitelist
     */
    public function getImageIdFilter(ImageType $type): ?array
    {
        $resultSet = null;

        foreach ($this->getSortedFilters() as $filter) {
            $filterResult = $filter->getImageIdFilter($type);

            if ($filterResult === null) {
                continue;
            }

            if ($filterResult === []) {
                return [];
            }

            if ($resultSet === null) {
                $resultSet = $filterResult;
            } else {
                $resultSet = array_values(array_intersect($resultSet, $filterResult));
                if ($resultSet === []) {
                    return [];
                }
            }
        }

        return $resultSet;
    }

    /**
     * Apply the combined filter to an array of images, returning only allowed ones.
     *
     * @param array<Image> $images
     * @return array<Image>
     */
    public function applyFilter(array $images, ImageType $type): array
    {
        $allowedIds = $this->getImageIdFilter($type);

        if ($allowedIds === null) {
            return $images;
        }

        if ($allowedIds === []) {
            return [];
        }

        return array_values(array_filter($images, fn(Image $img) => in_array($img->getId(), $allowedIds, true)));
    }

    /**
     * @return array<ImageGalleryFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort(
            $filters,
            static fn(
                ImageGalleryFilterInterface $a,
                ImageGalleryFilterInterface $b,
            ): int => $b->getPriority() <=> $a->getPriority(),
        );

        return $filters;
    }
}
