<?php declare(strict_types=1);

namespace Tests\Unit\Service\Member;

use App\Service\Member\MemberActionException;
use App\Service\Member\MemberActionFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class MemberActionExceptionTest extends TestCase
{
    public function testDefaultMessageFallsBackToFailureValue(): void
    {
        // Arrange / Act
        $exception = new MemberActionException(MemberActionFailure::SelfModification);

        // Assert
        static::assertSame(MemberActionFailure::SelfModification, $exception->failure);
        static::assertSame('self_modification', $exception->getMessage());
    }

    public function testCustomMessageOverridesFailureValue(): void
    {
        // Arrange / Act
        $exception = new MemberActionException(MemberActionFailure::SystemUser, 'cannot demote yourself');

        // Assert
        static::assertSame('cannot demote yourself', $exception->getMessage());
        static::assertSame(MemberActionFailure::SystemUser, $exception->failure);
    }

    #[DataProvider('provideFactoryCases')]
    public function testNamedFactoriesProduceMatchingFailure(callable $factory, MemberActionFailure $expected): void
    {
        // Act
        $exception = $factory();

        // Assert
        static::assertInstanceOf(MemberActionException::class, $exception);
        static::assertSame($expected, $exception->failure);
        static::assertSame($expected->value, $exception->getMessage());
    }

    public static function provideFactoryCases(): iterable
    {
        yield 'selfModification' => [
            static fn() => MemberActionException::selfModification(),
            MemberActionFailure::SelfModification,
        ];
        yield 'systemUser' => [
            static fn() => MemberActionException::systemUser(),
            MemberActionFailure::SystemUser,
        ];
        yield 'invalidRoleValue' => [
            static fn() => MemberActionException::invalidRoleValue(),
            MemberActionFailure::InvalidRoleValue,
        ];
        yield 'invalidFlagName' => [
            static fn() => MemberActionException::invalidFlagName(),
            MemberActionFailure::InvalidFlagName,
        ];
        yield 'invalidStatusTransition' => [
            static fn() => MemberActionException::invalidStatusTransition(),
            MemberActionFailure::InvalidStatusTransition,
        ];
        yield 'invalidGroupRoleValue' => [
            static fn() => MemberActionException::invalidGroupRoleValue(),
            MemberActionFailure::InvalidGroupRoleValue,
        ];
        yield 'invalidGroupRoleTransition' => [
            static fn() => MemberActionException::invalidGroupRoleTransition(),
            MemberActionFailure::InvalidGroupRoleTransition,
        ];
        yield 'membershipNotFound' => [
            static fn() => MemberActionException::membershipNotFound(),
            MemberActionFailure::MembershipNotFound,
        ];
        yield 'noOp' => [
            static fn() => MemberActionException::noOp(),
            MemberActionFailure::NoOp,
        ];
    }
}
