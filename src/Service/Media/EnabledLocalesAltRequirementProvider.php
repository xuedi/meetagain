<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use App\Service\Config\LanguageService;
use Override;

readonly class EnabledLocalesAltRequirementProvider implements AltLocaleRequirementProviderInterface
{
    public function __construct(private LanguageService $languageService) {}

    #[Override]
    public function getRequiredAltLocales(Image $image): array
    {
        return $this->languageService->getEnabledCodes();
    }

    #[Override]
    public function getRequiredAltLocalesForImages(array $images): array
    {
        $codes = $this->languageService->getEnabledCodes();

        $result = [];
        foreach ($images as $image) {
            $result[(int) $image->getId()] = $codes;
        }

        return $result;
    }
}
