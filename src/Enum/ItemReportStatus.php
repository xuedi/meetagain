<?php declare(strict_types=1);

namespace App\Enum;

enum ItemReportStatus: string
{
    case Open = 'open';
    case Kept = 'kept';
    case Removed = 'removed';

    public function label(): string
    {
        return 'item_report.status_' . $this->value;
    }
}
