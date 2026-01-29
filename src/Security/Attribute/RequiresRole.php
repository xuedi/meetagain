<?php declare(strict_types=1);

namespace App\Security\Attribute;

use App\Entity\UserRole;
use Attribute;

#[Attribute(Attribute::TARGET_CLASS)]
readonly class RequiresRole
{
    public function __construct(
        public UserRole $role,
    ) {}
}
