<?php declare(strict_types=1);

namespace App\Entity;

enum UserRole: string
{
    case MetaAdmin = 'META_ADMIN';
    case Admin = 'ADMIN';
    case Organizer = 'MANAGER';
    case User = 'USER';

    public function toRoleString(): string
    {
        return 'ROLE_' . $this->value;
    }

    public static function fromRoleString(string $role): self
    {
        return match ($role) {
            'ROLE_ADMIN' => self::Admin,
            'ROLE_META_ADMIN' => self::MetaAdmin,
            'ROLE_MANAGER' => self::Organizer,
            'ROLE_USER' => self::User,
            default => self::User,
        };
    }
}
