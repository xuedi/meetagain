<?php declare(strict_types=1);

namespace App\Portability;

use App\Entity\User;

readonly class PurgeContext
{
    public function __construct(
        public User $admin,
        public User $systemUser,
    ) {}
}
