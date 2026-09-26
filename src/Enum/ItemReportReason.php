<?php declare(strict_types=1);

namespace App\Enum;

enum ItemReportReason: string
{
    case Copyright = 'copyright';
    case Inappropriate = 'inappropriate';
    case Privacy = 'privacy';
    case Other = 'other';

    public function label(): string
    {
        return 'item_report.reason_' . $this->value;
    }
}
