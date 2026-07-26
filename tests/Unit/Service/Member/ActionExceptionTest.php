<?php declare(strict_types=1);

namespace Tests\Unit\Service\Member;

use App\Service\Member\ActionException;
use App\Service\Member\ActionFailure;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class ActionExceptionTest extends TestCase
{
    public function testDefaultMessageFallsBackToFailureValue(): void
    {
        // Arrange / Act
        $exception = new ActionException(ActionFailure::SelfModification);

        // Assert
        static::assertSame(ActionFailure::SelfModification, $exception->failure);
        static::assertSame('self_modification', $exception->getMessage());
    }

    public function testCustomMessageOverridesFailureValue(): void
    {
        // Arrange / Act
        $exception = new ActionException(ActionFailure::SystemUser, 'cannot demote yourself');

        // Assert
        static::assertSame('cannot demote yourself', $exception->getMessage());
        static::assertSame(ActionFailure::SystemUser, $exception->failure);
    }

    #[DataProvider('provideFactoryCases')]
    public function testNamedFactoriesProduceMatchingFailure(callable $factory, ActionFailure $expected): void
    {
        // Act
        $exception = $factory();

        // Assert
        static::assertInstanceOf(ActionException::class, $exception);
        static::assertSame($expected, $exception->failure);
        static::assertSame($expected->value, $exception->getMessage());
    }

    public static function provideFactoryCases(): iterable
    {
        yield 'selfModification' => [
            static fn() => ActionException::selfModification(),
            ActionFailure::SelfModification,
        ];
        yield 'systemUser' => [
            static fn() => ActionException::systemUser(),
            ActionFailure::SystemUser,
        ];
        yield 'invalidRoleValue' => [
            static fn() => ActionException::invalidRoleValue(),
            ActionFailure::InvalidRoleValue,
        ];
        yield 'invalidFlagName' => [
            static fn() => ActionException::invalidFlagName(),
            ActionFailure::InvalidFlagName,
        ];
        yield 'invalidStatusTransition' => [
            static fn() => ActionException::invalidStatusTransition(),
            ActionFailure::InvalidStatusTransition,
        ];
        yield 'invalidGroupRoleValue' => [
            static fn() => ActionException::invalidGroupRoleValue(),
            ActionFailure::InvalidGroupRoleValue,
        ];
        yield 'invalidGroupRoleTransition' => [
            static fn() => ActionException::invalidGroupRoleTransition(),
            ActionFailure::InvalidGroupRoleTransition,
        ];
        yield 'membershipNotFound' => [
            static fn() => ActionException::membershipNotFound(),
            ActionFailure::MembershipNotFound,
        ];
        yield 'noOp' => [
            static fn() => ActionException::noOp(),
            ActionFailure::NoOp,
        ];
    }
}
