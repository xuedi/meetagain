<?php declare(strict_types=1);

namespace App\Permission;

/**
 * A role a plugin contributes to the permission system. Implementations expose a stable
 * string identifier used to persist and compare role assignments.
 */
interface RoleInterface
{
    public function roleId(): string;
}
