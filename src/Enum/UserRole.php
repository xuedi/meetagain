<?php declare(strict_types=1);

namespace App\Enum;

use Symfony\Contracts\Translation\TranslatorInterface;

enum UserRole: string
{
    case Admin = 'ADMIN';
    case User = 'USER';
    case System = 'SYSTEM';

    // Constants for Symfony role strings (used in security.yaml, getRoles(), isGranted())
    public const ROLE_ADMIN = 'ROLE_ADMIN';
    public const ROLE_USER = 'ROLE_USER';
    public const ROLE_SYSTEM = 'ROLE_SYSTEM';

    public static function getChoices(TranslatorInterface $translator): array
    {
        return [
            $translator->trans('admin_member.role_admin') => self::Admin,
            $translator->trans('admin_member.role_user') => self::User,
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
