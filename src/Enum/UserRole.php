<?php declare(strict_types=1);

namespace App\Enum;

enum UserRole: string
{
    case Admin = 'ADMIN';
    case User = 'USER';
    case System = 'SYSTEM';

    // Constants for Symfony role strings (used in security.yaml, getRoles(), isGranted())
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_SYSTEM = 'ROLE_SYSTEM';

    public static function getChoices(): array
    {
        return [
            'Administrator' => self::Admin,
            'User' => self::User,
        ];
    }

    public function toRoleString(): string
    {
        return 'ROLE_' . $this->value;
    }

    public static function fromRoleString(string $role): self
    {
        return match ($role) {
            'ROLE_ADMIN' => self::Admin,
            'ROLE_SYSTEM' => self::System,
            default => self::User,
        };
    }
}
