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
}
