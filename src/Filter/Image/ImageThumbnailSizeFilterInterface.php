<?php declare(strict_types=1);

namespace App\Filter\Image;

use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface ImageThumbnailSizeFilterInterface
{
    /**
     * @return array<int, array{0: int, 1: int}>|null Returns the [width, height] pairs for the given
     *         ImageType, or null if this filter does not handle that type.
     */
    public function getThumbnailSizes(ImageType $type): ?array;

    /**
     * Returns the fit mode for the given ImageType, or null if this filter does not handle the type
     * (in which case the default Crop mode is used by core).
     */
    public function getFitMode(ImageType $type): ?ImageFitMode;
}
