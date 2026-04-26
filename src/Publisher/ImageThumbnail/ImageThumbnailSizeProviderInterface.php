<?php declare(strict_types=1);

namespace App\Publisher\ImageThumbnail;

use App\Enum\ImageFitMode;
use App\Enum\ImageType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Extension point for plugins to supply thumbnail sizes Implementations
 * return null for any ImageType they do not handle so the core ConfigService
 * can fall through to its built-in match.
 */
#[AutoconfigureTag]
interface ImageThumbnailSizeProviderInterface
{
    /**
     * @return array<int, array{0: int, 1: int}>|null Returns the [width, height] pairs for the given
     *         ImageType, or null if this provider does not handle that type.
     */
    public function getThumbnailSizes(ImageType $type): ?array;

    /**
     * Returns the fit mode for the given ImageType, or null if this provider does not handle the type
     * (in which case the default Crop mode is used by core).
     */
    public function getFitMode(ImageType $type): ?ImageFitMode;
}
