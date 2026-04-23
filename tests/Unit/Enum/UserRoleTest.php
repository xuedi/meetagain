<?php declare(strict_types=1);

namespace Tests\Unit\Enum;

use App\Enum\UserRole;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

class UserRoleTest extends TestCase
{
    public function testGetChoicesReturnsTranslationKeys(): void
    {
        // Arrange
        $translator = new IdentityTranslator();

        // Act
        $choices = UserRole::getChoices($translator);

        // Assert - IdentityTranslator returns keys unchanged, so array keys are translation keys
        static::assertSame(UserRole::Admin, $choices['admin_member.role_admin']);
        static::assertSame(UserRole::User,  $choices['admin_member.role_user']);
        static::assertCount(2, $choices, 'System role must not be exposed as a form choice');
    }

    public function testToRoleStringProducesSymfonyRoleName(): void
    {
        // Act & Assert
        static::assertSame(UserRole::ROLE_ADMIN,  UserRole::Admin->toRoleString());
        static::assertSame(UserRole::ROLE_USER,   UserRole::User->toRoleString());
        static::assertSame(UserRole::ROLE_SYSTEM, UserRole::System->toRoleString());
    }

    public function testFromRoleStringRoundTrips(): void
    {
        // Act & Assert
        static::assertSame(UserRole::Admin,  UserRole::fromRoleString(UserRole::ROLE_ADMIN));
        static::assertSame(UserRole::System, UserRole::fromRoleString(UserRole::ROLE_SYSTEM));
        static::assertSame(UserRole::User,   UserRole::fromRoleString(UserRole::ROLE_USER));
        static::assertSame(UserRole::User,   UserRole::fromRoleString('ROLE_UNKNOWN'));
    }
}
