<?php

declare(strict_types=1);

namespace App\Filter\Action;

use App\Entity\User;

readonly class ActionAuthorizationService
{
    private array $providers;

    public function __construct(iterable $providers)
    {
        $providersArray = iterator_to_array($providers);
        usort($providersArray, static fn($a, $b) => $b->getPriority() <=> $a->getPriority());
        $this->providers = $providersArray;
    }

    public function isActionAllowed(string $action, int $eventId, ?User $user): bool
    {
        foreach ($this->providers as $provider) {
            $result = $provider->canPerformAction($action, $eventId, $user);
            if ($result === false) {
                return false;
            }
        }

        return true;
    }
}
