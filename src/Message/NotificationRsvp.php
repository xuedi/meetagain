<?php declare(strict_types=1);

namespace App\Message;

use App\Entity\User;

readonly class NotificationRsvp
{
    public static function fromParameter(User $user, int $event): self
    {
        return new self($user, $event);
    }

    private function __construct(private User $user, private int $event)
    {
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function getEvent(): int
    {
        return $this->event;
    }

}
