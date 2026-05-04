<?php declare(strict_types=1);

namespace App\Security\Permission;

use App\Entity\User;

final readonly class PermissionContext
{
    public function __construct(
        public ?User $actor,
        public mixed $subject,
        public bool $isAdmin,
    ) {}
}
