<?php declare(strict_types=1);

namespace App\Service\Support;

use App\Entity\SupportRequest;
use App\Entity\User;
use App\Repository\UserRepository;
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;
use Traversable;

readonly class RecipientResolver
{
    /**
     * @param Traversable<RecipientProviderInterface> $providers
     */
    public function __construct(
        #[AutowireIterator(RecipientProviderInterface::class)]
        private Traversable $providers,
        private UserRepository $userRepository,
    ) {}

    /**
     * @return User[]
     */
    public function resolve(SupportRequest $request): array
    {
        foreach ($this->providers as $provider) {
            $recipients = $provider->getRecipients($request);
            if ($recipients !== null && $recipients !== []) {
                return $recipients;
            }
        }

        return $this->resolveAdmins();
    }

    /**
     * @return User[]
     */
    public function resolveAdmins(): array
    {
        return $this->userRepository->findAdminUsers();
    }
}
