<?php declare(strict_types=1);

namespace App\Enum;

enum ItemReportRelationship: string
{
    case Rightsholder = 'rightsholder';
    case Representative = 'representative';
    case Other = 'other';

    public function label(): string
    {
        return 'item_report.relationship_' . $this->value;
    }
}
