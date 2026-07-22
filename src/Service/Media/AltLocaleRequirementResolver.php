<?php declare(strict_types=1);

namespace App\Service\Media;

use App\Entity\Image;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class AltLocaleRequirementResolver
{
    /**
     * @param iterable<AltLocaleRequirementProviderInterface> $providers priority-sorted, highest first
     */
    public function __construct(
        #[AutowireIterator(AltLocaleRequirementProviderInterface::class)]
        private iterable $providers,
    ) {}

    /** @return list<string> */
    public function getRequiredAltLocales(Image $image): array
    {
        foreach ($this->providers as $provider) {
            $codes = $provider->getRequiredAltLocales($image);
            if ($codes !== null) {
                return $codes;
            }
        }

        return [];
    }

    /**
     * @param list<Image> $images
     * @return array<int, list<string>> imageId => required codes ([] when every provider defers)
     */
    public function getRequiredAltLocalesForImages(array $images): array
    {
        $resolved = [];
        $remaining = $images;
        foreach ($this->providers as $provider) {
            if ($remaining === []) {
                break;
            }

            $results = $provider->getRequiredAltLocalesForImages($remaining);
            $deferred = [];
            foreach ($remaining as $image) {
                $codes = $results[(int) $image->getId()] ?? null;
                if ($codes === null) {
                    $deferred[] = $image;
                } else {
                    $resolved[(int) $image->getId()] = $codes;
                }
            }
            $remaining = $deferred;
        }

        foreach ($remaining as $image) {
            $resolved[(int) $image->getId()] = [];
        }

        return $resolved;
    }
}
