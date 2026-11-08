<?php declare(strict_types=1);

namespace Module\Task\Contract;

enum ActionSection: string
{
    case ReferenceData = 'reference_data';
    case Recalculate = 'recalculate';
    case Defaults = 'defaults';
    case Diagnostics = 'diagnostics';
    case Backup = 'backup';

    public function label(): string
    {
        return 'admin_maintenance.section_' . $this->value;
    }
}
