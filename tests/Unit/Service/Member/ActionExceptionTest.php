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
            ActionException::selfModification(...),
            ActionFailure::SelfModification,
        ];
        yield 'systemUser' => [
            ActionException::systemUser(...),
            ActionFailure::SystemUser,
        ];
        yield 'invalidRoleValue' => [
            ActionException::invalidRoleValue(...),
            ActionFailure::InvalidRoleValue,
        ];
        yield 'invalidFlagName' => [
            ActionException::invalidFlagName(...),
            ActionFailure::InvalidFlagName,
        ];
        yield 'invalidStatusTransition' => [
            ActionException::invalidStatusTransition(...),
            ActionFailure::InvalidStatusTransition,
        ];
        yield 'invalidGroupRoleValue' => [
            ActionException::invalidGroupRoleValue(...),
            ActionFailure::InvalidGroupRoleValue,
        ];
        yield 'invalidGroupRoleTransition' => [
            ActionException::invalidGroupRoleTransition(...),
            ActionFailure::InvalidGroupRoleTransition,
        ];
        yield 'membershipNotFound' => [
            ActionException::membershipNotFound(...),
            ActionFailure::MembershipNotFound,
        ];
        yield 'noOp' => [
            ActionException::noOp(...),
            ActionFailure::NoOp,
        ];
    }
}
