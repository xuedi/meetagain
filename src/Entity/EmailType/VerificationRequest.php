<?php declare(strict_types=1);

namespace App\Entity\EmailType;

use App\Entity\User;

class VerificationRequest implements EmailInterface
{
    public static function create(User $user): self
    {
        return new self($user);
    }

    private function __construct(public User $user)
    {
    }

    public function getUser(): User
    {
        return $this->user;
    }
}
