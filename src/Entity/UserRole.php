<?php declare(strict_types=1);

namespace App\Entity;

enum UserRole: string
{
    case Admin = 'ADMIN';
    case Founder = 'FOUNDER';
    case Organizer = 'ORGANIZER';
    case User = 'USER';

    // Constants for use in attributes like #[IsGranted()]
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_FOUNDER = 'ROLE_FOUNDER';
    public const ROLE_ORGANIZER = 'ROLE_ORGANIZER';
    public const ROLE_USER = 'ROLE_USER';

    public static function getChoices(): array
    {
        return [
            'Administrator' => self::Admin,
            'Founder' => self::Founder,
            'Organizer' => self::Organizer,
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
            'ROLE_FOUNDER' => self::Founder,
            'ROLE_ORGANIZER' => self::Organizer,
            'ROLE_MANAGER' => self::Organizer, // Backwards compatibility
            'ROLE_USER' => self::User,
            default => self::User,
        };
    }
}
