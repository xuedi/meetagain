<?php declare(strict_types=1);

namespace App\Enum;

enum ImageReportStatus: string
{
    case Open = 'open';
    case Resolved = 'resolved';
}
