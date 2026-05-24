<?php declare(strict_types=1);

namespace Plugin\Ranking\Enum;

enum RankChangeReason: string
{
    case SelfEdit = 'self_edit';
    case AdminOverride = 'admin_override';
    case Import = 'import';
    case InitialAssignment = 'initial_assignment';
}
