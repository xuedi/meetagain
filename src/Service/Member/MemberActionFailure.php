<?php declare(strict_types=1);

namespace App\Service\Member;

enum MemberActionFailure: string
{
    case SelfModification = 'self_modification';
    case SystemUser = 'system_user';
    case InvalidRoleValue = 'invalid_role_value';
    case InvalidFlagName = 'invalid_flag_name';
    case InvalidStatusTransition = 'invalid_status_transition';
    case InvalidGroupRoleValue = 'invalid_group_role_value';
    case InvalidGroupRoleTransition = 'invalid_group_role_transition';
    case MembershipNotFound = 'membership_not_found';
    case NoOp = 'no_op';
}
