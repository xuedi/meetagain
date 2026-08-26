<?php declare(strict_types=1);

namespace App\Circulation\Trust;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class EnabledResolver
{
    /**
     * @param iterable<EnabledProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(EnabledProviderInterface::class)]
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
