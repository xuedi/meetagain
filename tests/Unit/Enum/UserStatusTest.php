<?php declare(strict_types=1);

namespace Tests\Unit\Enum;

use App\Enum\UserStatus;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Translation\IdentityTranslator;

class UserStatusTest extends TestCase
{
    #[DataProvider('provideLabelCases')]
    public function testLabelReturnsTranslationKey(UserStatus $status, string $expected): void
    {
        // Act
        $actual = $status->label();

        // Assert
        static::assertSame($expected, $actual);
    }

    public static function provideLabelCases(): iterable
    {
        yield 'registered'     => [UserStatus::Registered,    'admin_member.status_registered'];
        yield 'email_verified' => [UserStatus::EmailVerified, 'admin_member.status_email_verified'];
        yield 'active'         => [UserStatus::Active,        'admin_member.status_active'];
        yield 'blocked'        => [UserStatus::Blocked,       'admin_member.status_blocked'];
        yield 'deleted'        => [UserStatus::Deleted,       'admin_member.status_deleted'];
        yield 'denied'         => [UserStatus::Denied,        'admin_member.status_denied'];
    }

    public function testGetChoicesReturnsAllCasesKeyedByTranslationKey(): void
    {
        // Arrange
        $translator = new IdentityTranslator();

        // Act
        $choices = UserStatus::getChoices($translator);

        // Assert - IdentityTranslator returns keys unchanged, so array keys are translation keys
        static::assertSame(UserStatus::Registered,    $choices['admin_member.status_registered']);
        static::assertSame(UserStatus::EmailVerified, $choices['admin_member.status_email_verified']);
        static::assertSame(UserStatus::Active,        $choices['admin_member.status_active']);
        static::assertSame(UserStatus::Blocked,       $choices['admin_member.status_blocked']);
        static::assertSame(UserStatus::Deleted,       $choices['admin_member.status_deleted']);
        static::assertSame(UserStatus::Denied,        $choices['admin_member.status_denied']);
        static::assertCount(6, $choices);
    }
}
