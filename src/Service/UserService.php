<?php declare(strict_types=1);

namespace App\Service;

use App\Repository\UserRepository;

readonly class UserService
{
    public function __construct(
        private UserRepository $userRepo,
    ) {
    }

    public function resolveUserName(int $id): string
    {
        return $this->userRepo->resolveUserName($id);
    }
}
