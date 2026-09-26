<?php declare(strict_types=1);

namespace Module\Circulation\Internal;

use App\Entity\User;
use Module\Circulation\Contract\EligibilityProviderInterface;
use Module\Circulation\Contract\EligibilityVerdict;
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
            $verdict = $provider->canRequest($context, $itemType, $itemId, (int) $user->getId());
            if ($verdict !== null && !$verdict->allowed) {
                return $verdict;
            }
        }

        return EligibilityVerdict::allowed();
    }
}
