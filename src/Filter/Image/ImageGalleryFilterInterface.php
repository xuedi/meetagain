<?php declare(strict_types=1);

namespace App\Filter\Image;

use App\Enum\ImageType;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

/**
 * Interface for image gallery filters.
 * Plugins can implement this to restrict which images are visible in the gallery.
 *
 * Multiple filters can be registered - they are composed with AND logic.
 * If any filter restricts an image, it will be hidden.
 */
#[AutoconfigureTag]
interface ImageGalleryFilterInterface
{
    /**
     * Get priority for filter ordering.
     * Higher priority filters are applied first.
     * Default: 0
     */
    public function getPriority(): int;

    /**
     * Get the allowed image IDs for the current context.
     *
     * @return array<int>|null Returns:
     *         - null: No filtering (allow all images)
     *         - array<int>: Only these image IDs are allowed
     *         - []: No images allowed (empty result)
     */
    public function getImageIdFilter(ImageType $type): ?array;
}
