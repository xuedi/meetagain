<?php declare(strict_types=1);

namespace App\Twig;

use App\Entity\Image;
use App\Service\Media\AltLocaleRequirementResolver;
use Twig\Extension\RuntimeExtensionInterface;

final readonly class AltLocaleRuntime implements RuntimeExtensionInterface
{
    public function __construct(private AltLocaleRequirementResolver $resolver) {}

    /** @return list<string> */
    public function getRequiredAltLocales(Image $image): array
    {
        return $this->resolver->getRequiredAltLocales($image);
    }
}
