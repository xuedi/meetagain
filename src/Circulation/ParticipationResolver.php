<?php declare(strict_types=1);

namespace App\Circulation;

use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class ParticipationResolver
{
    /**
     * @param iterable<ParticipationProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(ParticipationProviderInterface::class)]
        private iterable $providers,
    ) {}

    public function isEnabled(string $itemType): bool
    {
        foreach ($this->providers as $provider) {
            $enabled = $provider->isEnabled($itemType);
            if ($enabled !== null) {
                return $enabled;
            }
        }

        return false;
    }
}
