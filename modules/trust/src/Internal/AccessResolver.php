<?php declare(strict_types=1);

namespace Module\Trust\Internal;

use App\Entity\User;
use Module\Trust\Contract\AccessProviderInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

final readonly class AccessResolver
{
    /**
     * @param iterable<AccessProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(AccessProviderInterface::class)]
        private iterable $providers,
        private Security $security,
    ) {}

    public function canView(string $context, int $userId): bool
    {
        foreach ($this->providers as $provider) {
            $answer = $provider->canView($context, $userId);
            if ($answer !== null) {
                return $answer;
            }
        }

        return $this->isOperator($userId);
    }

    public function canAdminister(string $context, int $userId): bool
    {
        foreach ($this->providers as $provider) {
            $answer = $provider->canAdminister($context, $userId);
            if ($answer !== null) {
                return $answer;
            }
        }

        return $this->isOperator($userId);
    }

    public function getViewerId(): ?int
    {
        $user = $this->security->getUser();

        return $user instanceof User ? $user->getId() : null;
    }

    private function isOperator(int $userId): bool
    {
        return $userId === $this->getViewerId() && $this->security->isGranted('ROLE_ADMIN');
    }
}
