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
        $id = $request->getId();
        if ($id === null) {
            return $this->resolveAdmins();
        }

        return $this->resolveMany([$request])[$id];
    }

    /**
     * @param list<SupportRequest> $requests
     * @return array<int, User[]> request id => recipients
     */
    public function resolveMany(array $requests): array
    {
        $pending = [];
        foreach ($requests as $request) {
            $id = $request->getId();
            if ($id !== null) {
                $pending[$id] = $request;
            }
        }

        $recipients = [];
        foreach ($this->providers as $provider) {
            if ($pending === []) {
                break;
            }
            foreach ($provider->getRecipients(array_values($pending)) as $id => $claimed) {
                if ($claimed !== [] && isset($pending[$id])) {
                    $recipients[$id] = $claimed;
                    unset($pending[$id]);
                }
            }
        }

        if ($pending !== []) {
            $admins = $this->resolveAdmins();
            foreach (array_keys($pending) as $id) {
                $recipients[$id] = $admins;
            }
        }

        return $recipients;
    }

    /**
     * @return User[]
     */
    public function resolveAdmins(): array
    {
        return $this->userRepository->findAdminUsers();
    }
}
