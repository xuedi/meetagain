<?php declare(strict_types=1);

namespace App\Filter\Image;

use App\Enum\ImageType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Narrows the images visible in the gallery. Implementations compose with AND-intersection.
 */
#[AutoconfigureTag]
interface ImageGalleryFilterInterface
{
    /**
     * Higher priority runs first. Default: 0.
     */
    public function getPriority(): int;

    /**
     * @return array<int>|null null = no opinion, [] = block all, [id, ...] = allow-list
     */
    public function getImageIdFilter(ImageType $type): ?array;
}
