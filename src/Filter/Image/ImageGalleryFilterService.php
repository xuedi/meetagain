<?php declare(strict_types=1);

namespace App\Filter\Image;

use App\Entity\Image;
use App\Enum\ImageType;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

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

        return array_values(array_filter($images, static fn(Image $img) => in_array($img->getId(), $allowedIds, true)));
    }

    /**
     * @return array<ImageGalleryFilterInterface>
     */
    private function getSortedFilters(): array
    {
        $filters = iterator_to_array($this->filters);

        usort($filters, static fn(ImageGalleryFilterInterface $a, ImageGalleryFilterInterface $b): int => $b->getPriority() <=> $a->getPriority());

        return $filters;
    }
}
