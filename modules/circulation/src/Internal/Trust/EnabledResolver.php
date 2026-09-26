<?php declare(strict_types=1);

namespace Module\Circulation\Internal\Trust;

use Module\Circulation\Contract\TrustEnabledProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class EnabledResolver
{
    /**
     * @param iterable<TrustEnabledProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(TrustEnabledProviderInterface::class)]
        private iterable $providers,
    ) {}

    public function isEnabled(string $itemType): bool
    {
        foreach ($this->providers as $provider) {
            $enabled = $provider->isTrustEnabled($itemType);
            if ($enabled !== null) {
                return $enabled;
            }
        }

        return false;
    }
}
