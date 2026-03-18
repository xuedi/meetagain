<?php

declare(strict_types=1);

namespace App\Filter\Action;

use App\Entity\User;

interface ActionAuthorizationInterface
{
    public function getPriority(): int;

    /**
     * Check if user can perform action on event.
     *
     * @return bool|null - true = allow, false = deny, null = no opinion
     */
    public function canPerformAction(string $action, int $eventId, ?User $user): ?bool;
}
