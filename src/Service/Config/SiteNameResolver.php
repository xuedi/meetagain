<?php declare(strict_types=1);

namespace App\Service\Config;

use App\Publisher\SiteName\SiteNameProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

readonly class SiteNameResolver
{
    /**
     * @param iterable<SiteNameProviderInterface> $providers
     */
    public function __construct(
        private ConfigService $configService,
        #[AutowireIterator(SiteNameProviderInterface::class)]
        private iterable $providers = [],
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
}
