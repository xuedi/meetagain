<?php declare(strict_types=1);

namespace App\Service\Config;

use App\Publisher\SiteName\SiteNameProviderInterface;
use App\Publisher\SiteName\TitlePlaceProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class SiteNameResolver
{
    /**
     * @param iterable<SiteNameProviderInterface>  $providers
     * @param iterable<TitlePlaceProviderInterface> $placeProviders
     */
    public function __construct(
        private ConfigService $configService,
        #[AutowireIterator(SiteNameProviderInterface::class)]
        private iterable $providers = [],
        #[AutowireIterator(TitlePlaceProviderInterface::class)]
        private iterable $placeProviders = [],
    ) {}

    public function resolve(): string
    {
        foreach ($this->providers as $provider) {
            $value = $provider->getSiteName();
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return $this->configService->getSiteName();
    }

    public function resolvePlace(string $locale): ?string
    {
        foreach ($this->placeProviders as $provider) {
            $value = $provider->getTitlePlace($locale);
            if ($value !== null && $value !== '') {
                return $value;
            }
        }

        return null;
    }
}
