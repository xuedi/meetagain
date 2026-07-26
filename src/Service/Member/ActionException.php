<?php declare(strict_types=1);

namespace App\Service\Member;

use RuntimeException;

final class ActionException extends RuntimeException
{
    public function __construct(
        public readonly ActionFailure $failure,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $failure->value);
    }

    public static function selfModification(): self
    {
        return new self(ActionFailure::SelfModification);
    }

    public static function systemUser(): self
    {
        return new self(ActionFailure::SystemUser);
    }

    public static function invalidRoleValue(): self
    {
        return new self(ActionFailure::InvalidRoleValue);
    }

    public static function invalidFlagName(): self
    {
        return new self(ActionFailure::InvalidFlagName);
    }

    public static function invalidStatusTransition(): self
    {
        return new self(ActionFailure::InvalidStatusTransition);
    }

    public static function invalidGroupRoleValue(): self
    {
        return new self(ActionFailure::InvalidGroupRoleValue);
    }

    public static function invalidGroupRoleTransition(): self
    {
        return new self(ActionFailure::InvalidGroupRoleTransition);
    }

    public static function membershipNotFound(): self
    {
        return new self(ActionFailure::MembershipNotFound);
    }

    public static function noOp(): self
    {
        return new self(ActionFailure::NoOp);
    }
}
