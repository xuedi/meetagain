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
}
