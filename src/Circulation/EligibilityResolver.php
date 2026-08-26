<?php declare(strict_types=1);

namespace App\Circulation;

use App\Entity\User;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class EligibilityResolver
{
    /**
     * @param iterable<EligibilityProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(EligibilityProviderInterface::class)]
        private iterable $providers,
    ) {}

    public function resolve(string $context, string $itemType, int $itemId, User $user): EligibilityVerdict
    {
        foreach ($this->providers as $provider) {
            $verdict = $provider->canRequest($context, $itemType, $itemId, $user);
            if ($verdict !== null && !$verdict->allowed) {
                return $verdict;
            }
        }

        return EligibilityVerdict::allowed();
    }
}
