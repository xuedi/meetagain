<?php

declare(strict_types=1);

namespace App\Authorization\Action;

use App\Entity\User;
use App\Enum\FlashMessageType;

readonly class ActionAuthorizationMessageService
{
    private array $providers;

    public function __construct(iterable $providers)
    {
        $providersArray = iterator_to_array($providers);
        usort($providersArray, fn($a, $b) => $b->getPriority() <=> $a->getPriority());
        $this->providers = $providersArray;
    }

    public function getUnauthorizedMessage(string $action, int $eventId, ?User $user): UnauthorizedMessage
    {
        foreach ($this->providers as $provider) {
            $message = $provider->getUnauthorizedMessage($action, $eventId, $user);
            if ($message !== null) {
                return $message;
            }
        }

        $actionLabel = match ($action) {
            'event.rsvp' => 'RSVP',
            'event.comment' => 'comment',
            'event.upload' => 'upload images',
            default => 'perform this action',
        };

        return new UnauthorizedMessage(
            message: sprintf('This event is for group members only. Please join the group to %s.', $actionLabel),
            type: FlashMessageType::Error,
        );
    }
}
