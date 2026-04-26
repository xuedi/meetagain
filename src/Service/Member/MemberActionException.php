<?php declare(strict_types=1);

namespace App\Service\Member;

use RuntimeException;

final class MemberActionException extends RuntimeException
{
    public function __construct(
        public readonly MemberActionFailure $failure,
        string $message = '',
    ) {
        parent::__construct($message !== '' ? $message : $failure->value);
    }

    public static function selfModification(): self
    {
        return new self(MemberActionFailure::SelfModification);
    }

    public static function systemUser(): self
    {
        return new self(MemberActionFailure::SystemUser);
    }

    public static function invalidRoleValue(): self
    {
        return new self(MemberActionFailure::InvalidRoleValue);
    }

    public static function invalidFlagName(): self
    {
        return new self(MemberActionFailure::InvalidFlagName);
    }

    public static function invalidStatusTransition(): self
    {
        return new self(MemberActionFailure::InvalidStatusTransition);
    }

    public static function invalidGroupRoleValue(): self
    {
        return new self(MemberActionFailure::InvalidGroupRoleValue);
    }

    public static function invalidGroupRoleTransition(): self
    {
        return new self(MemberActionFailure::InvalidGroupRoleTransition);
    }

    public static function membershipNotFound(): self
    {
        return new self(MemberActionFailure::MembershipNotFound);
    }

    public static function noOp(): self
    {
        return new self(MemberActionFailure::NoOp);
    }
}
