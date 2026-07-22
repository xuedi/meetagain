<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use Symfony\Component\DependencyInjection\Attribute\AutoconfigureTag;

#[AutoconfigureTag]
interface AltLocaleRequirementProviderInterface
{
    /**
     * The locales that must each carry their own alt text for this image, or null to defer to the
     * next provider. Higher-priority implementations run first; the first non-null result wins.
     *
     * @return list<string>|null
     */
    public function getRequiredAltLocales(Image $image): ?array;

    /**
     * Batch counterpart of getRequiredAltLocales() for a page of images: the same per-image
     * decision, keyed by image ID (null defers that image to the next provider).
     *
     * @param list<Image> $images
     * @return array<int, list<string>|null>
     */
    public function getRequiredAltLocalesForImages(array $images): array;
}
