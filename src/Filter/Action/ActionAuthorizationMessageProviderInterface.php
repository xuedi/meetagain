<?php

declare(strict_types=1);

namespace App\Filter\Action;

use App\Entity\User;

interface ActionAuthorizationMessageProviderInterface
{
    public function getPriority(): int;

    /**
     * Provide a custom error message for unauthorized action.
     *
     * @return UnauthorizedMessage|null - Custom message or null to use default
     */
    public function getUnauthorizedMessage(string $action, int $eventId, ?User $user): ?UnauthorizedMessage;
}
